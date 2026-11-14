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
