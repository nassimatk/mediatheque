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
}
