<?php

namespace App\Repository;

use App\Entity\Edition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Edition>
 */
class EditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Edition::class);
    }

    /**
     * Top des éditions les plus empruntées (avec le nombre d'emprunts).
     *
     * @return array<int, array{edition: Edition, loanCount: int}>
     */
    public function getTopBorrowedEditions(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e, COUNT(l.id) AS loanCount')
            ->join('e.copies', 'c')
            ->join('App\Entity\Loan', 'l', 'WITH', 'l.copy = c')
            ->join('e.work', 'w')
            ->groupBy('e.id')
            ->orderBy('loanCount', 'DESC')
            ->addOrderBy('w.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn($row) => [
            'edition'   => $row[0],
            'loanCount' => (int) $row['loanCount'],
        ], $rows);
    }

    /**
     * Documents jamais empruntés (éditions ayant au moins 1 exemplaire en rayon mais 0 prêt enregistré).
     *
     * @return Edition[]
     */
    public function findDormantEditions(int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.work', 'w')
            ->join('e.copies', 'c')
            ->leftJoin('App\Entity\Loan', 'l', 'WITH', 'l.copy = c')
            ->groupBy('e.id')
            ->having('COUNT(l.id) = 0')
            ->orderBy('w.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
