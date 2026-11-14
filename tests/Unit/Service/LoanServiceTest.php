<?php

namespace App\Tests\Unit\Service;

use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\SubscriptionPlan;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Service\Circulation\LoanService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires complets de LoanService (règles de prêt, retour, concurrence et circulation).
 */
class LoanServiceTest extends TestCase
{
    private LoanService $service;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var LoanRepository&MockObject */
    private LoanRepository $loanRepo;

    /** @var PenaltyRepository&MockObject */
    private PenaltyRepository $penaltyRepo;

    /** @var ReservationRepository&MockObject */
    private ReservationRepository $reservationRepo;

    protected function setUp(): void
    {
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->loanRepo        = $this->createMock(LoanRepository::class);
        $this->penaltyRepo     = $this->createMock(PenaltyRepository::class);
        $this->reservationRepo = $this->createMock(ReservationRepository::class);

        // Configuration pour exécuter directement le callback passé à wrapInTransaction
        $this->em->method('wrapInTransaction')->willReturnCallback(fn(callable $cb) => $cb($this->em));

        $this->service = new LoanService(
            $this->em,
            $this->loanRepo,
            $this->penaltyRepo,
            $this->reservationRepo,
            reservationHoldDays: 5
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makePlan(int $maxLoans = 3, int $loanDays = 21, int $maxRenewals = 1, int $penaltyCents = 100): SubscriptionPlan
    {
        $plan = new SubscriptionPlan();
        $plan->setMaxLoans($maxLoans)
             ->setLoanDays($loanDays)
             ->setMaxRenewals($maxRenewals)
             ->setPenaltyCentsPerDay($penaltyCents);

        return $plan;
    }

    private function makeMember(SubscriptionPlan $plan, int $id = 1): Member
    {
        $member = new Member();
        $ref = new \ReflectionProperty(Member::class, 'id');
        $ref->setValue($member, $id);

        $member->setFirstName('Test')->setLastName('User')->setEmail('test@test.ma');
        $member->setPlan($plan);

        return $member;
    }

    private function makeCopy(CopyStatus $status = CopyStatus::Available, ?Site $site = null): Copy
    {
        $edition = new Edition();
        $copy    = new Copy();
        $copy->setStatus($status)->setEdition($edition);
        if ($site !== null) {
            $copy->setSite($site);
        }

        return $copy;
    }

    private function makeOpenLoan(Member $member, Copy $copy, int $daysAgo = 0, int $daysUntilDue = 7): Loan
    {
        $now  = new \DateTimeImmutable();
        $due  = $daysUntilDue >= 0
            ? $now->modify("+{$daysUntilDue} days")
            : $now->modify(abs($daysUntilDue).' days ago');

        $loan = new Loan();
        $loan->setMember($member)
             ->setCopy($copy)
             ->setBorrowedAt($daysAgo > 0 ? $now->modify("-{$daysAgo} days") : $now)
             ->setDueAt($due)
             ->setRenewalCount(0);

        return $loan;
    }

    // =========================================================================
    // checkout()
    // =========================================================================

    public function testCheckoutSucceedsForAvailableCopy(): void
    {
        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::Available);

        $this->loanRepo->method('countOpenLoansForMember')->willReturn(0);
        $this->penaltyRepo->method('hasUnpaidPenalty')->willReturn(false);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $loan = $this->service->checkout($member, $copy);

        $this->assertInstanceOf(Loan::class, $loan);
        $this->assertSame($member, $loan->getMember());
        $this->assertSame($copy, $loan->getCopy());
        $this->assertSame(CopyStatus::Borrowed, $copy->getStatus());
        $this->assertSame(0, $loan->getRenewalCount());
    }

    public function testCheckoutSucceedsForReservedCopyWhenMemberIsReserver(): void
    {
        $plan   = $this->makePlan();
        $member = $this->makeMember($plan, id: 42);
        $copy   = $this->makeCopy(CopyStatus::Reserved);

        $reservation = new Reservation();
        $reservation->setMember($member)
                    ->setCopy($copy)
                    ->setStatus(ReservationStatus::Ready);

        $this->reservationRepo->method('findReadyReservationForCopy')->with($copy)->willReturn($reservation);
        $this->loanRepo->method('countOpenLoansForMember')->willReturn(0);
        $this->penaltyRepo->method('hasUnpaidPenalty')->willReturn(false);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $loan = $this->service->checkout($member, $copy);

        $this->assertSame(CopyStatus::Borrowed, $copy->getStatus());
        $this->assertSame(ReservationStatus::Fulfilled, $reservation->getStatus(), 'La réservation doit être honorée');
    }

    public function testCheckoutFailsForReservedCopyWhenMemberIsNotReserver(): void
    {
        $this->expectException(LoanException::class);

        $plan     = $this->makePlan();
        $memberA  = $this->makeMember($plan, id: 1);
        $memberB  = $this->makeMember($plan, id: 2);
        $copy     = $this->makeCopy(CopyStatus::Reserved);

        $reservation = new Reservation();
        $reservation->setMember($memberA) // Réservé pour membre A
                    ->setCopy($copy)
                    ->setStatus(ReservationStatus::Ready);

        $this->reservationRepo->method('findReadyReservationForCopy')->with($copy)->willReturn($reservation);

        // Membre B tente d'emprunter l'exemplaire de A => doit être rejeté
        $this->service->checkout($memberB, $copy);
    }

    public function testCheckoutFailsWhenCopyInTransit(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::InTransit);

        $this->service->checkout($member, $copy);
    }

    public function testCheckoutFailsWhenCopyNotAvailable(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::Borrowed);

        $this->service->checkout($member, $copy);
    }

    public function testCheckoutFailsWhenQuotaReached(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan(maxLoans: 2);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::Available);

        $this->loanRepo->method('countOpenLoansForMember')->willReturn(2);
        $this->penaltyRepo->method('hasUnpaidPenalty')->willReturn(false);

        $this->service->checkout($member, $copy);
    }

    public function testCheckoutBlockedByUnpaidPenalty(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::Available);

        $this->loanRepo->method('countOpenLoansForMember')->willReturn(0);
        $this->penaltyRepo->method('hasUnpaidPenalty')->willReturn(true);

        $this->service->checkout($member, $copy);
    }

    // =========================================================================
    // renew()
    // =========================================================================

    public function testRenewSucceeds(): void
    {
        $plan   = $this->makePlan(maxRenewals: 2, loanDays: 21);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy);
        $loan->setRenewalCount(1);

        $this->reservationRepo->method('hasPendingReservation')->willReturn(false);
        $this->em->expects($this->once())->method('flush');

        $originalDue = $loan->getDueAt();
        $renewed     = $this->service->renew($loan);

        $this->assertSame(2, $renewed->getRenewalCount());
        $this->assertGreaterThan($originalDue, $renewed->getDueAt());
    }

    public function testRenewFailsWhenMaxRenewalsReached(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan(maxRenewals: 1);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy);
        $loan->setRenewalCount(1);

        $this->service->renew($loan);
    }

    public function testRenewFailsWhenReservationExists(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan(maxRenewals: 2);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy);
        $loan->setRenewalCount(0);

        $this->reservationRepo->method('hasPendingReservation')->willReturn(true);

        $this->service->renew($loan);
    }

    // =========================================================================
    // return() & Circulation inter-sites
    // =========================================================================

    public function testReturnClosesLoanWithoutPenaltyWhenOnTime(): void
    {
        $plan   = $this->makePlan(penaltyCents: 100);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy, daysAgo: 10, daysUntilDue: 5);

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn(null);
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->return($loan);

        $this->assertNotNull($loan->getClosedAt());
        $this->assertSame(CopyStatus::Available, $copy->getStatus());
    }

    public function testReturnCreatesLatePenalty(): void
    {
        $plan   = $this->makePlan(penaltyCents: 100);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy, daysAgo: 24, daysUntilDue: -3);

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->return($loan);

        $this->assertNotNull($loan->getClosedAt());
    }

    public function testReturnNotifiesNextReservationOnSameSite(): void
    {
        $site = new Site();
        $ref  = new \ReflectionProperty(Site::class, 'id');
        $ref->setValue($site, 1);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(site: $site);
        $loan   = $this->makeOpenLoan($member, $copy, daysUntilDue: 5);

        $reservation = new Reservation();
        $reservation->setMember($member)
                    ->setEdition($copy->getEdition())
                    ->setPickupSite($site)
                    ->setStatus(ReservationStatus::Pending);

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn($reservation);
        $this->em->expects($this->once())->method('flush');

        $this->service->return($loan, returnSite: $site);

        $this->assertSame(ReservationStatus::Ready, $reservation->getStatus());
        $this->assertSame($copy, $reservation->getCopy());
        $this->assertNotNull($reservation->getHoldUntil());
        $this->assertSame(CopyStatus::Reserved, $copy->getStatus());
    }

    public function testReturnPutsCopyInTransitWhenReservationIsOnDifferentSite(): void
    {
        $siteDepart = new Site();
        $ref = new \ReflectionProperty(Site::class, 'id');
        $ref->setValue($siteDepart, 1);

        $siteRetrait = new Site();
        $ref->setValue($siteRetrait, 2);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(site: $siteDepart);
        $loan   = $this->makeOpenLoan($member, $copy, daysUntilDue: 5);

        // La réservation suivante est au site 2, alors que le livre est déposé au site 1
        $reservation = new Reservation();
        $reservation->setMember($member)
                    ->setEdition($copy->getEdition())
                    ->setPickupSite($siteRetrait)
                    ->setStatus(ReservationStatus::Pending);

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn($reservation);
        $this->em->expects($this->once())->method('flush');

        $this->service->return($loan, returnSite: $siteDepart);

        // L'exemplaire doit voyager vers le site 2
        $this->assertSame(CopyStatus::InTransit, $copy->getStatus());
        $this->assertSame($siteRetrait, $copy->getDestinationSite());
        $this->assertSame($copy, $reservation->getCopy());
        $this->assertSame(ReservationStatus::Pending, $reservation->getStatus(), 'Reste Pending tant qu\'il voyage');
    }
}
