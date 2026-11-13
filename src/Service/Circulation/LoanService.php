<?php

namespace App\Service\Circulation;

use App\Entity\Copy;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Enum\CopyStatus;
use App\Enum\PenaltyType;
use App\Enum\ReservationStatus;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des prêts.
 *
 * Toutes les règles métier de circulation se trouvent ici,
 * jamais dans les contrôleurs ni dans les entités.
 */
class LoanService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoanRepository        $loanRepo,
        private readonly PenaltyRepository     $penaltyRepo,
        private readonly ReservationRepository $reservationRepo,
    ) {}

    // -------------------------------------------------------------------------
    // Emprunter
    // -------------------------------------------------------------------------

    /**
     * Crée un prêt pour $member sur $copy.
     *
     * Règles vérifiées :
     *  1. L'exemplaire doit être Available.
     *  2. L'adhérent ne doit pas avoir atteint son quota de prêts ouverts.
     *  3. L'adhérent ne doit pas avoir de pénalité impayée.
     *
     * @throws LoanException
     */
    public function checkout(Member $member, Copy $copy): Loan
    {
        // 1. Exemplaire disponible ?
        if ($copy->getStatus() !== CopyStatus::Available) {
            throw LoanException::copyNotAvailable();
        }

        $plan = $member->getPlan();

        // 2. Quota de prêts simultanés
        $openLoans = $this->loanRepo->countOpenLoansForMember($member);
        if ($openLoans >= $plan->getMaxLoans()) {
            throw LoanException::quotaReached($plan->getMaxLoans());
        }

        // 3. Pénalité impayée bloquante
        if ($this->penaltyRepo->hasUnpaidPenalty($member)) {
            throw LoanException::unpaidPenalty();
        }

        // Création du prêt
        $now  = new \DateTimeImmutable();
        $loan = new Loan();
        $loan->setMember($member)
             ->setCopy($copy)
             ->setBorrowedAt($now)
             ->setDueAt($now->modify(sprintf('+%d days', $plan->getLoanDays())))
             ->setRenewalCount(0);

        $copy->setStatus(CopyStatus::Borrowed);

        $this->em->persist($loan);
        $this->em->flush();

        return $loan;
    }

    // -------------------------------------------------------------------------
    // Prolonger
    // -------------------------------------------------------------------------

    /**
     * Prolonge un prêt ouvert d'une durée égale à loanDays.
     *
     * Règles vérifiées :
     *  1. Le prêt doit être ouvert.
     *  2. renewalCount < plan.maxRenewals.
     *  3. Aucune réservation Pending sur la même édition.
     *
     * @throws LoanException
     */
    public function renew(Loan $loan): Loan
    {
        // 1. Prêt ouvert ?
        if (!$loan->isOpen()) {
            throw LoanException::loanAlreadyClosed();
        }

        $plan = $loan->getMember()->getPlan();

        // 2. Renouvellements restants ?
        if ($loan->getRenewalCount() >= $plan->getMaxRenewals()) {
            throw LoanException::maxRenewalsReached($plan->getMaxRenewals());
        }

        // 3. Réservation en attente sur cette édition ?
        $edition = $loan->getCopy()->getEdition();
        if ($this->reservationRepo->hasPendingReservation($edition)) {
            throw LoanException::reservationExists();
        }

        $loan->setDueAt(
            $loan->getDueAt()->modify(sprintf('+%d days', $plan->getLoanDays()))
        );
        $loan->setRenewalCount($loan->getRenewalCount() + 1);

        $this->em->flush();

        return $loan;
    }

    // -------------------------------------------------------------------------
    // Retourner
    // -------------------------------------------------------------------------

    /**
     * Clôture un prêt et applique les règles post-retour :
     *  - Calcule une pénalité si le retour est en retard.
     *  - Notifie la prochaine réservation FIFO (Ready + holdUntil +5j).
     *  - Met à jour le statut de l'exemplaire.
     *
     * @param CopyStatus|null $newStatus  Available par défaut ; Damaged ou Lost si signalé.
     * @throws LoanException
     */
    public function return(Loan $loan, ?CopyStatus $newStatus = null): void
    {
        if (!$loan->isOpen()) {
            throw LoanException::loanAlreadyClosed();
        }

        $now       = new \DateTimeImmutable();
        $copyStatus = $newStatus ?? CopyStatus::Available;

        // Clôture du prêt
        $loan->setClosedAt($now);

        // Pénalité de retard
        if ($loan->getDueAt() < $now && $copyStatus !== CopyStatus::Lost) {
            $this->applyLatePenalty($loan, $now);
        }

        // Pénalité perte / dégât (montant forfaitaire fixe : 5 000 centimes)
        if ($copyStatus === CopyStatus::Lost) {
            $this->applySpecialPenalty($loan, PenaltyType::Lost, 5000);
        } elseif ($copyStatus === CopyStatus::Damaged) {
            $this->applySpecialPenalty($loan, PenaltyType::Damaged, 2000);
        }

        // Mise à jour de l'exemplaire
        $copy = $loan->getCopy();
        $copy->setStatus($copyStatus);

        // Notification de la prochaine réservation FIFO (uniquement si exemplaire toujours physique)
        if ($copyStatus === CopyStatus::Available) {
            $this->notifyNextReservation($copy, $now);
        }

        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private function applyLatePenalty(Loan $loan, \DateTimeImmutable $now): void
    {
        $daysLate = (int) $loan->getDueAt()->diff($now)->days;
        if ($daysLate <= 0) {
            return;
        }

        $rate    = $loan->getMember()->getPlan()->getPenaltyCentsPerDay();
        $penalty = new Penalty();
        $penalty->setLoan($loan)
                ->setType(PenaltyType::Late)
                ->setAmountCents($daysLate * $rate)
                ->setCreatedAt($now);

        $this->em->persist($penalty);
    }

    private function applySpecialPenalty(Loan $loan, PenaltyType $type, int $amountCents): void
    {
        $penalty = new Penalty();
        $penalty->setLoan($loan)
                ->setType($type)
                ->setAmountCents($amountCents)
                ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($penalty);
    }

    private function notifyNextReservation(Copy $copy, \DateTimeImmutable $now): void
    {
        $edition     = $copy->getEdition();
        $reservation = $this->reservationRepo->findNextPendingForEdition($edition);

        if ($reservation === null) {
            return;
        }

        $reservation->setStatus(ReservationStatus::Ready)
                    ->setCopy($copy)
                    ->setHoldUntil($now->modify('+5 days'));

        // L'exemplaire est désormais mis de côté
        $copy->setStatus(CopyStatus::Reserved);
    }
}
