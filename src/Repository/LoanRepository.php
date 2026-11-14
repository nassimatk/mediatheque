<?php

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Nombre de prêts ouverts (non clôturés) pour un adhérent donné.
     */
    public function countOpenLoansForMember(Member $member): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.member = :member')
            ->andWhere('l.closedAt IS NULL')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Prêts ouverts pour un adhérent (liste).
     *
     * @return Loan[]
     */
    public function findOpenLoansForMember(Member $member): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.member = :member')
            ->andWhere('l.closedAt IS NULL')
            ->orderBy('l.dueAt', 'ASC')
            ->setParameter('member', $member)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prêts passés (historique clôturé) pour un adhérent.
     *
     * @return Loan[]
     */
    public function findClosedLoansForMember(Member $member, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.member = :member')
            ->andWhere('l.closedAt IS NOT NULL')
            ->orderBy('l.closedAt', 'DESC')
            ->setParameter('member', $member)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre total de prêts enregistrés dans l'application.
     */
    public function countTotalLoans(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
