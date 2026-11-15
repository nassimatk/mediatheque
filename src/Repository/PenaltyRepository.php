<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Penalty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Penalty>
 */
class PenaltyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Penalty::class);
    }

    /**
     * Vérifie si un adhérent a au moins une pénalité non payée.
     * Utilisé par LoanService::checkout() pour bloquer les emprunts.
     */
    public function hasUnpaidPenalty(Member $member): bool
    {
        $count = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.loan', 'l')
            ->where('l.member = :member')
            ->andWhere('p.paidAt IS NULL')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Retourne toutes les pénalités impayées (pour affichage et gestion au comptoir).
     *
     * @return Penalty[]
     */
    public function findUnpaidPenalties(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.loan', 'l')
            ->addSelect('l')
            ->join('l.member', 'm')
            ->addSelect('m')
            ->where('p.paidAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
