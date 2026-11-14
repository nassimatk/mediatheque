<?php

namespace App\Service\Circulation;

use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\ReservationException;
use App\Repository\CopyRepository;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des réservations et de la circulation des documents.
 */
class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository  $reservationRepo,
        private readonly CopyRepository         $copyRepo,
        private readonly int                    $reservationHoldDays = 5,
    ) {}

    /**
     * Crée une réservation pour $member sur $edition avec retrait au site $pickupSite.
     *
     * Règles appliquées :
     *  1. Interdit si le membre a déjà une réservation active sur cette édition.
     *  2. Interdit si un exemplaire est DIRECTEMENT disponible sur les étagères de $pickupSite.
     *  3. Si un exemplaire est disponible sur l'AUTRE site : il est réservé et part immédiatement
     *     en transit vers $pickupSite (décision Q2).
     *  4. Si tous les exemplaires sont empruntés/indisponibles sur le réseau : placé en file d'attente FIFO (Pending).
     *
     * @throws ReservationException
     */
    public function reserve(Member $member, Edition $edition, Site $pickupSite): Reservation
    {
        return $this->em->wrapInTransaction(function () use ($member, $edition, $pickupSite): Reservation {
            // Règle 1 : pas de double réservation active
            $existing = $this->reservationRepo->findActiveForMemberAndEdition($member, $edition);
            if ($existing !== null) {
                throw ReservationException::alreadyReserved();
            }

            // Règle 2 : document disponible sur place => aller le chercher directement
            $localAvailableCopy = $this->copyRepo->findFirstAvailableOnSite($edition, $pickupSite);
            if ($localAvailableCopy !== null) {
                throw ReservationException::copyAvailableOnSite();
            }

            $now = new \DateTimeImmutable();
            $reservation = new Reservation();
            $reservation->setMember($member)
                        ->setEdition($edition)
                        ->setPickupSite($pickupSite)
                        ->setCreatedAt($now);

            // Règle 3 : document disponible sur l'autre site => déclencher le transfert inter-sites (Q2)
            $otherAvailableCopy = $this->copyRepo->findFirstAvailableOnOtherSite($edition, $pickupSite);
            if ($otherAvailableCopy !== null) {
                if ($otherAvailableCopy->getId() !== null) {
                    $this->em->lock($otherAvailableCopy, LockMode::PESSIMISTIC_WRITE);
                }

                $otherAvailableCopy->setStatus(CopyStatus::InTransit);
                $otherAvailableCopy->setDestinationSite($pickupSite);

                $reservation->setCopy($otherAvailableCopy);
                $reservation->setStatus(ReservationStatus::Pending); // En attente de voyage
            } else {
                // Règle 4 : Aucun exemplaire disponible nulle part => file d'attente FIFO
                $reservation->setStatus(ReservationStatus::Pending);
            }

            $this->em->persist($reservation);
            $this->em->flush();

            return $reservation;
        });
    }

    /**
     * Valide l'arrivée physique d'un exemplaire en transit dans sa médiathèque de destination.
     *
     * @throws ReservationException
     */
    public function receiveTransit(Copy $copy, Site $arrivalSite): void
    {
        $this->em->wrapInTransaction(function () use ($copy, $arrivalSite): void {
            if ($copy->getId() !== null) {
                $this->em->lock($copy, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($copy);
            }

            if ($copy->getStatus() !== CopyStatus::InTransit) {
                throw ReservationException::copyNotInTransit();
            }

            if ($copy->getDestinationSite() === null || $copy->getDestinationSite()->getId() !== $arrivalSite->getId()) {
                throw ReservationException::invalidTransitDestination();
            }

            $now = new \DateTimeImmutable();
            $copy->setSite($arrivalSite);
            $copy->setDestinationSite(null);

            // Vérifier si une réservation attendait cet exemplaire
            $reservation = $this->reservationRepo->findActiveReservationForCopy($copy);

            if ($reservation !== null && $reservation->getPickupSite()->getId() === $arrivalSite->getId()) {
                $copy->setStatus(CopyStatus::Reserved);
                $reservation->setStatus(ReservationStatus::Ready);
                $reservation->setHoldUntil($now->modify(sprintf('+%d days', $this->reservationHoldDays)));
            } else {
                // Si aucune réservation directe, vérifier si une autre réservation attend sur ce site
                $next = $this->reservationRepo->findNextPendingForEdition($copy->getEdition());
                if ($next !== null && $next->getPickupSite()->getId() === $arrivalSite->getId()) {
                    $copy->setStatus(CopyStatus::Reserved);
                    $next->setStatus(ReservationStatus::Ready);
                    $next->setCopy($copy);
                    $next->setHoldUntil($now->modify(sprintf('+%d days', $this->reservationHoldDays)));
                } else {
                    $copy->setStatus(CopyStatus::Available);
                }
            }

            $this->em->flush();
        });
    }

    /**
     * Annule une réservation active. Libère l'exemplaire attribué ou le passe au suivant.
     *
     * @throws ReservationException
     */
    public function cancel(Reservation $reservation): void
    {
        $this->em->wrapInTransaction(function () use ($reservation): void {
            $status = $reservation->getStatus();

            if (!in_array($status, [ReservationStatus::Pending, ReservationStatus::Ready], true)) {
                throw ReservationException::reservationNotActive();
            }

            $copy = $reservation->getCopy();
            $now  = new \DateTimeImmutable();

            if ($copy !== null) {
                if ($copy->getId() !== null) {
                    $this->em->lock($copy, LockMode::PESSIMISTIC_WRITE);
                }

                $reservation->setCopy(null);

                // Attribuer l'exemplaire au suivant dans la file FIFO ou le libérer
                $next = $this->reservationRepo->findNextPendingForEdition($reservation->getEdition());

                if ($next !== null) {
                    if ($copy->getSite() !== null && $next->getPickupSite()->getId() === $copy->getSite()->getId()) {
                        $copy->setStatus(CopyStatus::Reserved);
                        $copy->setDestinationSite(null);
                        $next->setStatus(ReservationStatus::Ready);
                        $next->setCopy($copy);
                        $next->setHoldUntil($now->modify(sprintf('+%d days', $this->reservationHoldDays)));
                    } else {
                        $copy->setStatus(CopyStatus::InTransit);
                        $copy->setDestinationSite($next->getPickupSite());
                        $next->setCopy($copy);
                    }
                } else {
                    $copy->setStatus(CopyStatus::Available);
                    $copy->setDestinationSite(null);
                }
            }

            $reservation->setStatus(ReservationStatus::Cancelled);
            $this->em->flush();
        });
    }

    /**
     * Expire les réservations prêtes (Ready) dont la date limite de retrait est dépassée (décision Q3).
     * Les exemplaires sont réattribués à la réservation suivante ou remis en rayon.
     *
     * @return int Nombre de réservations expirées
     */
    public function expireOutdatedReservations(?\DateTimeImmutable $now = null): int
    {
        return $this->em->wrapInTransaction(function () use ($now): int {
            $currentDate = $now ?? new \DateTimeImmutable();
            $outdated    = $this->reservationRepo->findOutdatedReadyReservations($currentDate);
            $count       = 0;

            foreach ($outdated as $reservation) {
                $copy = $reservation->getCopy();

                $reservation->setStatus(ReservationStatus::Expired);
                $reservation->setCopy(null);
                $count++;

                if ($copy !== null) {
                    if ($copy->getId() !== null) {
                        $this->em->lock($copy, LockMode::PESSIMISTIC_WRITE);
                    }

                    // Transférer au suivant de la file FIFO si disponible
                    $next = $this->reservationRepo->findNextPendingForEdition($reservation->getEdition());

                    if ($next !== null) {
                        if ($copy->getSite() !== null && $next->getPickupSite()->getId() === $copy->getSite()->getId()) {
                            $copy->setStatus(CopyStatus::Reserved);
                            $copy->setDestinationSite(null);
                            $next->setStatus(ReservationStatus::Ready);
                            $next->setCopy($copy);
                            $next->setHoldUntil($currentDate->modify(sprintf('+%d days', $this->reservationHoldDays)));
                        } else {
                            $copy->setStatus(CopyStatus::InTransit);
                            $copy->setDestinationSite($next->getPickupSite());
                            $next->setCopy($copy);
                        }
                    } else {
                        $copy->setStatus(CopyStatus::Available);
                        $copy->setDestinationSite(null);
                    }
                }
            }

            $this->em->flush();

            return $count;
        });
    }
}
