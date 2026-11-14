<?php

namespace App\Service\Circulation;

use App\Entity\Copy;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Entity\Site;
use App\Enum\CopyStatus;
use App\Enum\PenaltyType;
use App\Enum\ReservationStatus;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des prêts et retours.
 *
 * Toutes les règles métier de circulation et de concurrence se trouvent ici.
 */
class LoanService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoanRepository        $loanRepo,
        private readonly PenaltyRepository     $penaltyRepo,
        private readonly ReservationRepository $reservationRepo,
        private readonly int                   $reservationHoldDays = 5,
    ) {}

    // -------------------------------------------------------------------------
    // Emprunter (Checkout)
    // -------------------------------------------------------------------------

    /**
     * Crée un prêt pour $member sur $copy dans une transaction avec verrou pessimiste.
     *
     * Règles vérifiées :
     *  1. Verrouillage pessimiste de la ligne Copy en base.
     *  2. L'exemplaire ne doit pas être en transit, perdu ou abîmé.
     *  3. Si l'exemplaire est réservé (Reserved), l'emprunt n'est autorisé que pour le membre réservant.
     *     La réservation passe alors à "Fulfilled" (honorée).
     *  4. L'adhérent ne doit pas avoir atteint son quota de prêts ouverts.
     *  5. L'adhérent ne doit pas avoir de pénalité impayée.
     *
     * @throws LoanException
     */
    public function checkout(Member $member, Copy $copy): Loan
    {
        return $this->em->wrapInTransaction(function () use ($member, $copy): Loan {
            // Verrouillage pessimiste pour éviter les emprunts simultanés
            if ($copy->getId() !== null) {
                $this->em->lock($copy, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($copy);
            }

            // 1. Vérification du statut de l'exemplaire
            if ($copy->getStatus() === CopyStatus::InTransit) {
                throw LoanException::copyInTransit();
            }

            $reservationToFulfill = null;
            if ($copy->getStatus() === CopyStatus::Reserved) {
                $reservation = $this->reservationRepo->findReadyReservationForCopy($copy);
                if ($reservation === null || $reservation->getMember()->getId() !== $member->getId()) {
                    throw LoanException::copyReservedForAnotherMember();
                }
                $reservationToFulfill = $reservation;
            } elseif ($copy->getStatus() !== CopyStatus::Available) {
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

            // 4. Création du prêt
            $now  = new \DateTimeImmutable();
            $loan = new Loan();
            $loan->setMember($member)
                 ->setCopy($copy)
                 ->setBorrowedAt($now)
                 ->setDueAt($now->modify(sprintf('+%d days', $plan->getLoanDays())))
                 ->setRenewalCount(0);

            $copy->setStatus(CopyStatus::Borrowed);

            // Si l'emprunt honore une réservation mise de côté, on clôture la réservation
            if ($reservationToFulfill !== null) {
                $reservationToFulfill->setStatus(ReservationStatus::Fulfilled);
            }

            $this->em->persist($loan);
            $this->em->flush();

            return $loan;
        });
    }

    // -------------------------------------------------------------------------
    // Prolonger (Renew)
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
        return $this->em->wrapInTransaction(function () use ($loan): Loan {
            if (!$loan->isOpen()) {
                throw LoanException::loanAlreadyClosed();
            }

            $plan = $loan->getMember()->getPlan();

            if ($loan->getRenewalCount() >= $plan->getMaxRenewals()) {
                throw LoanException::maxRenewalsReached($plan->getMaxRenewals());
            }

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
        });
    }

    // -------------------------------------------------------------------------
    // Retourner (Return)
    // -------------------------------------------------------------------------

    /**
     * Clôture un prêt, applique les pénalités de retard et gère la circulation :
     *  - Met à jour le site physique de l'exemplaire selon $returnSite (circulation multi-sites).
     *  - Si une réservation attend sur un AUTRE site : envoie l'exemplaire en transit vers ce site.
     *  - Si la réservation est sur le MÊME site : met l'exemplaire de côté (Reserved) avec holdUntil.
     *
     * @param Site|null       $returnSite Site où l'adhérent dépose le document (si omis, conserve le site actuel).
     * @param CopyStatus|null $newStatus  Available par défaut ; Damaged ou Lost si signalé.
     * @throws LoanException
     */
    public function return(Loan $loan, ?Site $returnSite = null, ?CopyStatus $newStatus = null): void
    {
        $this->em->wrapInTransaction(function () use ($loan, $returnSite, $newStatus): void {
            if (!$loan->isOpen()) {
                throw LoanException::loanAlreadyClosed();
            }

            $now        = new \DateTimeImmutable();
            $copyStatus = $newStatus ?? CopyStatus::Available;
            $copy       = $loan->getCopy();

            if ($copy->getId() !== null) {
                $this->em->lock($copy, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($copy);
            }

            // Clôture du prêt
            $loan->setClosedAt($now);

            // Mise à jour du site de l'exemplaire (retour dans n'importe quel site du réseau)
            if ($returnSite !== null) {
                $copy->setSite($returnSite);
            }

            // Pénalité de retard
            if ($loan->getDueAt() < $now && $copyStatus !== CopyStatus::Lost) {
                $this->applyLatePenalty($loan, $now);
            }

            // Pénalités forfaitaires pour perte ou dégradation
            if ($copyStatus === CopyStatus::Lost) {
                $this->applySpecialPenalty($loan, PenaltyType::Lost, 5000);
            } elseif ($copyStatus === CopyStatus::Damaged) {
                $this->applySpecialPenalty($loan, PenaltyType::Damaged, 2000);
            }

            // Circulation / Attribution à la réservation suivante
            if ($copyStatus === CopyStatus::Available) {
                $this->dispatchNextReservation($copy, $now);
            } else {
                $copy->setStatus($copyStatus);
                $copy->setDestinationSite(null);
            }

            $this->em->flush();
        });
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

    /**
     * Attribue l'exemplaire à la réservation suivante ou le remet en rayon.
     * Gère le transit inter-sites si le site de retrait diffère du site actuel.
     */
    private function dispatchNextReservation(Copy $copy, \DateTimeImmutable $now): void
    {
        $edition     = $copy->getEdition();
        $reservation = $this->reservationRepo->findNextPendingForEdition($edition);

        if ($reservation === null) {
            $copy->setStatus(CopyStatus::Available);
            $copy->setDestinationSite(null);
            return;
        }

        $pickupSite  = $reservation->getPickupSite();
        $currentSite = $copy->getSite();

        if ($currentSite !== null && $pickupSite->getId() === $currentSite->getId()) {
            // Même site : l'exemplaire est immédiatement mis de côté pour l'adhérent
            $reservation->setStatus(ReservationStatus::Ready)
                        ->setCopy($copy)
                        ->setHoldUntil($now->modify(sprintf('+%d days', $this->reservationHoldDays)));

            $copy->setStatus(CopyStatus::Reserved);
            $copy->setDestinationSite(null);
        } else {
            // Sites différents : l'exemplaire part en transit vers le site de retrait
            $copy->setStatus(CopyStatus::InTransit);
            $copy->setDestinationSite($pickupSite);

            $reservation->setCopy($copy);
            // La réservation reste Pending jusqu'à l'arrivée physique sur le site de retrait
        }
    }
}
