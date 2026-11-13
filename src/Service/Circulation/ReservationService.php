<?php

namespace App\Service\Circulation;

use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\ReservationException;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des réservations (file d'attente FIFO par édition).
 */
class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository  $reservationRepo,
    ) {}

    /**
     * Crée une réservation pour $member sur $edition au site $pickupSite.
     *
     * Règle : un adhérent ne peut pas avoir deux réservations actives
     * (Pending ou Ready) sur la même édition.
     *
     * @throws ReservationException
     */
    public function reserve(Member $member, Edition $edition, Site $pickupSite): Reservation
    {
        $existing = $this->reservationRepo->findActiveForMemberAndEdition($member, $edition);
        if ($existing !== null) {
            throw ReservationException::alreadyReserved();
        }

        $reservation = new Reservation();
        $reservation->setMember($member)
                    ->setEdition($edition)
                    ->setPickupSite($pickupSite)
                    ->setStatus(ReservationStatus::Pending)
                    ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    /**
     * Annule une réservation active.
     * Si la réservation était Ready (exemplaire mis de côté), l'exemplaire repasse en Available.
     *
     * @throws ReservationException
     */
    public function cancel(Reservation $reservation): void
    {
        $status = $reservation->getStatus();

        if (!in_array($status, [ReservationStatus::Pending, ReservationStatus::Ready], true)) {
            throw ReservationException::reservationNotActive();
        }

        // Libérer l'exemplaire mis de côté
        if ($status === ReservationStatus::Ready && $reservation->getCopy() !== null) {
            $reservation->getCopy()->setStatus(CopyStatus::Available);
            $reservation->setCopy(null);
        }

        $reservation->setStatus(ReservationStatus::Cancelled);

        $this->em->flush();
    }
}
