<?php

namespace App\Repository;

use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Vérifie qu'au moins une réservation Pending existe pour cette édition.
     * Utilisé par LoanService::renew() pour bloquer la prolongation.
     */
    public function hasPendingReservation(Edition $edition): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.edition = :edition')
            ->andWhere('r.status = :status')
            ->setParameter('edition', $edition)
            ->setParameter('status', ReservationStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Retourne la prochaine réservation Pending pour une édition (ordre FIFO par createdAt).
     * Utilisé par LoanService::return() pour notifier l'adhérent suivant.
     */
    public function findNextPendingForEdition(Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->where('r.edition = :edition')
            ->andWhere('r.status = :status')
            ->orderBy('r.createdAt', 'ASC')
            ->setMaxResults(1)
            ->setParameter('edition', $edition)
            ->setParameter('status', ReservationStatus::Pending)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réservation active (Pending ou Ready) pour un couple membre+édition.
     * Utilisé par ReservationService::reserve() pour éviter les doublons.
     */
    public function findActiveForMemberAndEdition(Member $member, Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->where('r.member = :member')
            ->andWhere('r.edition = :edition')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('member', $member)
            ->setParameter('edition', $edition)
            ->setParameter('statuses', [ReservationStatus::Pending, ReservationStatus::Ready])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
