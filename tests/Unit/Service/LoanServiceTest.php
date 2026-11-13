<?php

namespace App\Tests\Unit\Service;

use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\SubscriptionPlan;
use App\Enum\CopyStatus;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Service\Circulation\LoanService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de LoanService.
 * On utilise des mocks pour tout ce qui touche la base de données.
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

        $this->service = new LoanService(
            $this->em,
            $this->loanRepo,
            $this->penaltyRepo,
            $this->reservationRepo,
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

    private function makeMember(SubscriptionPlan $plan): Member
    {
        $member = new Member();
        $member->setFirstName('Test')->setLastName('User')->setEmail('test@test.ma');
        $member->setPlan($plan);

        return $member;
    }

    private function makeCopy(CopyStatus $status = CopyStatus::Available): Copy
    {
        $edition = new Edition();
        $copy    = new Copy();
        $copy->setStatus($status)->setEdition($edition);

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

    public function testCheckoutSucceeds(): void
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

    public function testCheckoutFailsWhenCopyNotAvailable(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::Borrowed); // pas disponible

        $this->service->checkout($member, $copy);
    }

    public function testCheckoutFailsWhenQuotaReached(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan(maxLoans: 2);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy(CopyStatus::Available);

        $this->loanRepo->method('countOpenLoansForMember')->willReturn(2); // quota atteint
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
        $this->penaltyRepo->method('hasUnpaidPenalty')->willReturn(true); // pénalité impayée

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
        $loan->setRenewalCount(1); // déjà au max

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

        $this->reservationRepo->method('hasPendingReservation')->willReturn(true); // réservation bloquante

        $this->service->renew($loan);
    }

    public function testRenewFailsWhenLoanClosed(): void
    {
        $this->expectException(LoanException::class);

        $plan   = $this->makePlan();
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy);
        $loan->setClosedAt(new \DateTimeImmutable()); // prêt clôturé

        $this->service->renew($loan);
    }

    // =========================================================================
    // return()
    // =========================================================================

    public function testReturnClosesLoanWithoutPenaltyWhenOnTime(): void
    {
        $plan   = $this->makePlan(penaltyCents: 100);
        $member = $this->makeMember($plan);
        $copy   = $this->makeCopy();
        $loan   = $this->makeOpenLoan($member, $copy, daysAgo: 10, daysUntilDue: 5); // pas encore en retard

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn(null);
        $this->em->expects($this->never())->method('persist'); // pas de pénalité
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
        // Prêt dont la date limite était il y a 3 jours
        $loan   = $this->makeOpenLoan($member, $copy, daysAgo: 24, daysUntilDue: -3);

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn(null);
        $this->em->expects($this->once())->method('persist'); // la pénalité
        $this->em->expects($this->once())->method('flush');

        $this->service->return($loan);

        $this->assertNotNull($loan->getClosedAt());
    }

    public function testReturnNotifiesNextReservation(): void
    {
        $plan        = $this->makePlan();
        $member      = $this->makeMember($plan);
        $copy        = $this->makeCopy();
        $loan        = $this->makeOpenLoan($member, $copy, daysUntilDue: 5);

        $reservation = new \App\Entity\Reservation();
        $reservation->setMember($member)
                    ->setEdition($copy->getEdition())
                    ->setPickupSite(new \App\Entity\Site())
                    ->setStatus(\App\Enum\ReservationStatus::Pending);

        $this->reservationRepo->method('findNextPendingForEdition')->willReturn($reservation);
        $this->em->expects($this->once())->method('flush');

        $this->service->return($loan);

        $this->assertSame(\App\Enum\ReservationStatus::Ready, $reservation->getStatus());
        $this->assertSame($copy, $reservation->getCopy());
        $this->assertNotNull($reservation->getHoldUntil());
        $this->assertSame(CopyStatus::Reserved, $copy->getStatus()); // mis de côté
    }
}
