<?php

namespace App\Repository;

use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Site;
use App\Enum\CopyStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Copy>
 */
class CopyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Copy::class);
    }

    /**
     * Trouve un exemplaire disponible sur un site spécifique.
     */
    public function findFirstAvailableOnSite(Edition $edition, Site $site): ?Copy
    {
        return $this->createQueryBuilder('c')
            ->where('c.edition = :edition')
            ->andWhere('c.site = :site')
            ->andWhere('c.status = :status')
            ->setParameter('edition', $edition)
            ->setParameter('site', $site)
            ->setParameter('status', CopyStatus::Available)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un exemplaire disponible sur un autre site que celui spécifié.
     */
    public function findFirstAvailableOnOtherSite(Edition $edition, Site $site): ?Copy
    {
        return $this->createQueryBuilder('c')
            ->where('c.edition = :edition')
            ->andWhere('c.site != :site')
            ->andWhere('c.status = :status')
            ->setParameter('edition', $edition)
            ->setParameter('site', $site)
            ->setParameter('status', CopyStatus::Available)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie s'il existe au moins un exemplaire disponible sur l'ensemble du réseau.
     */
    public function hasAnyAvailable(Edition $edition): bool
    {
        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.edition = :edition')
            ->andWhere('c.status = :status')
            ->setParameter('edition', $edition)
            ->setParameter('status', CopyStatus::Available)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
