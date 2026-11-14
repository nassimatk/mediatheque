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
     * Nombre total de prêts enregistrés dans l'application.
     */
    public function countTotalLoans(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top des éditions les plus empruntées (avec le nombre d'emprunts).
     *
     * @return array<int, array{edition: \App\Entity\Edition, loanCount: int}>
     */
    public function getTopBorrowedEditions(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('e AS edition, COUNT(l.id) AS loanCount')
            ->join('l.copy', 'c')
            ->join('c.edition', 'e')
            ->join('e.work', 'w')
            ->groupBy('e.id')
            ->orderBy('loanCount', 'DESC')
            ->addOrderBy('w.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn($row) => [
            'edition'   => $row['edition'],
            'loanCount' => (int) $row['loanCount'],
        ], $rows);
    }
}
