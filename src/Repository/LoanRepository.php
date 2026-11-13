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
}
