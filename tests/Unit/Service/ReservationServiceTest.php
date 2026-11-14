<?php

namespace App\Tests\Unit\Service;

use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\SubscriptionPlan;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\ReservationException;
use App\Repository\CopyRepository;
use App\Repository\ReservationRepository;
use App\Service\Circulation\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de ReservationService (file d'attente FIFO, circulation inter-sites et expiration).
 */
class ReservationServiceTest extends TestCase
{
    private ReservationService $service;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var ReservationRepository&MockObject */
    private ReservationRepository $reservationRepo;

    /** @var CopyRepository&MockObject */
    private CopyRepository $copyRepo;

    protected function setUp(): void
    {
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->reservationRepo = $this->createMock(ReservationRepository::class);
        $this->copyRepo        = $this->createMock(CopyRepository::class);

        $this->em->method('wrapInTransaction')->willReturnCallback(fn(callable $cb) => $cb($this->em));

        $this->service = new ReservationService(
            $this->em,
            $this->reservationRepo,
            $this->copyRepo,
            reservationHoldDays: 5
        );
    }

    private function makeSite(int $id, string $name): Site
    {
        $site = new Site();
        $ref  = new \ReflectionProperty(Site::class, 'id');
        $ref->setValue($site, $id);
        $site->setName($name);

        return $site;
    }

    private function makeMember(int $id = 1): Member
    {
        $member = new Member();
        $ref    = new \ReflectionProperty(Member::class, 'id');
        $ref->setValue($member, $id);
        $member->setPlan(new SubscriptionPlan());

        return $member;
    }

    // =========================================================================
    // reserve()
    // =========================================================================

    public function testReserveFailsWhenCopyAvailableAtPickupSite(): void
    {
        $this->expectException(ReservationException::class);

        $siteA   = $this->makeSite(1, 'Site Centre');
        $member  = $this->makeMember();
        $edition = new Edition();
        $copyA   = new Copy();
        $copyA->setSite($siteA)->setStatus(CopyStatus::Available);

        $this->reservationRepo->method('findActiveForMemberAndEdition')->willReturn(null);
        // Exemplaire disponible directement dans le site demandé
        $this->copyRepo->method('findFirstAvailableOnSite')->with($edition, $siteA)->willReturn($copyA);

        $this->service->reserve($member, $edition, $siteA);
    }

    public function testReserveTriggersTransitWhenCopyAvailableAtOtherSite(): void
    {
        $siteA   = $this->makeSite(1, 'Site Centre');
        $siteB   = $this->makeSite(2, 'Site Nord');
        $member  = $this->makeMember();
        $edition = new Edition();

        $copyB   = new Copy();
        $copyB->setSite($siteB)->setStatus(CopyStatus::Available);

        $this->reservationRepo->method('findActiveForMemberAndEdition')->willReturn(null);
        // Aucun exemplaire disponible à Site A
        $this->copyRepo->method('findFirstAvailableOnSite')->with($edition, $siteA)->willReturn(null);
        // Mais un exemplaire disponible à Site B (décision Q2)
        $this->copyRepo->method('findFirstAvailableOnOtherSite')->with($edition, $siteA)->willReturn($copyB);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $reservation = $this->service->reserve($member, $edition, $siteA);

        $this->assertSame(CopyStatus::InTransit, $copyB->getStatus(), 'L\'exemplaire doit passer en transit');
        $this->assertSame($siteA, $copyB->getDestinationSite(), 'La destination doit être le site de retrait');
        $this->assertSame($copyB, $reservation->getCopy());
        $this->assertSame(ReservationStatus::Pending, $reservation->getStatus());
    }

    public function testReserveEnqueuesFifoWhenNoCopiesAvailableNetworkWide(): void
    {
        $siteA   = $this->makeSite(1, 'Site Centre');
        $member  = $this->makeMember();
        $edition = new Edition();

        $this->reservationRepo->method('findActiveForMemberAndEdition')->willReturn(null);
        // Aucun exemplaire disponible nulle part
        $this->copyRepo->method('findFirstAvailableOnSite')->willReturn(null);
        $this->copyRepo->method('findFirstAvailableOnOtherSite')->willReturn(null);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $reservation = $this->service->reserve($member, $edition, $siteA);

        $this->assertNull($reservation->getCopy(), 'Aucun exemplaire attribué dans la file d\'attente');
        $this->assertSame(ReservationStatus::Pending, $reservation->getStatus());
    }

    public function testReserveFailsWhenAlreadyReserved(): void
    {
        $this->expectException(ReservationException::class);

        $siteA   = $this->makeSite(1, 'Site Centre');
        $member  = $this->makeMember();
        $edition = new Edition();

        $this->reservationRepo->method('findActiveForMemberAndEdition')->willReturn(new Reservation());

        $this->service->reserve($member, $edition, $siteA);
    }

    // =========================================================================
    // receiveTransit()
    // =========================================================================

    public function testReceiveTransitSucceedsAndSetsReservationReady(): void
    {
        $siteDestination = $this->makeSite(2, 'Site Nord');
        $edition         = new Edition();
        $member          = $this->makeMember();

        $copy = new Copy();
        $copy->setEdition($edition)
             ->setStatus(CopyStatus::InTransit)
             ->setDestinationSite($siteDestination);

        $reservation = new Reservation();
        $reservation->setMember($member)
                    ->setEdition($edition)
                    ->setPickupSite($siteDestination)
                    ->setCopy($copy)
                    ->setStatus(ReservationStatus::Pending);

        $this->reservationRepo->method('findActiveReservationForCopy')->with($copy)->willReturn($reservation);
        $this->em->expects($this->once())->method('flush');

        $this->service->receiveTransit($copy, $siteDestination);

        $this->assertSame(CopyStatus::Reserved, $copy->getStatus(), 'L\'exemplaire est désormais mis de côté');
        $this->assertSame($siteDestination, $copy->getSite());
        $this->assertNull($copy->getDestinationSite());
        $this->assertSame(ReservationStatus::Ready, $reservation->getStatus(), 'La réservation passe à Ready');
        $this->assertNotNull($reservation->getHoldUntil(), 'holdUntil doit être renseigné');
    }

    public function testReceiveTransitFailsWhenDestinationMismatch(): void
    {
        $this->expectException(ReservationException::class);

        $siteDestination = $this->makeSite(2, 'Site Nord');
        $siteWrong       = $this->makeSite(3, 'Autre Site');

        $copy = new Copy();
        $copy->setStatus(CopyStatus::InTransit)
             ->setDestinationSite($siteDestination);

        $this->service->receiveTransit($copy, $siteWrong);
    }

    // =========================================================================
    // expireOutdatedReservations()
    // =========================================================================

    public function testExpireOutdatedReservationsReassignsOrReleasesCopy(): void
    {
        $siteCentre = $this->makeSite(1, 'Site Centre');
        $memberA    = $this->makeMember(1);
        $edition    = new Edition();

        $copy = new Copy();
        $copy->setEdition($edition)
             ->setSite($siteCentre)
             ->setStatus(CopyStatus::Reserved);

        // Réservation échue (holdUntil était hier)
        $resExpired = new Reservation();
        $resExpired->setMember($memberA)
                   ->setEdition($edition)
                   ->setPickupSite($siteCentre)
                   ->setCopy($copy)
                   ->setStatus(ReservationStatus::Ready)
                   ->setHoldUntil(new \DateTimeImmutable('-1 day'));

        $this->reservationRepo->method('findOutdatedReadyReservations')->willReturn([$resExpired]);
        // Pas d'autre réservation dans la file d'attente
        $this->reservationRepo->method('findNextPendingForEdition')->willReturn(null);

        $this->em->expects($this->once())->method('flush');

        $count = $this->service->expireOutdatedReservations(new \DateTimeImmutable());

        $this->assertSame(1, $count);
        $this->assertSame(ReservationStatus::Expired, $resExpired->getStatus());
        $this->assertNull($resExpired->getCopy());
        $this->assertSame(CopyStatus::Available, $copy->getStatus(), 'L\'exemplaire doit repasser Available en rayon');
    }
}
