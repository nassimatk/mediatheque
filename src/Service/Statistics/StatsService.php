<?php

namespace App\Service\Statistics;

use App\Entity\Edition;
use App\Repository\CopyRepository;
use App\Repository\EditionRepository;
use App\Repository\LoanRepository;

/**
 * Service métier de calcul des statistiques du réseau de médiathèques.
 * Conforme au Niveau 3 : taux de rotation, top des emprunts, documents dormants.
 */
class StatsService
{
    public function __construct(
        private readonly LoanRepository    $loanRepo,
        private readonly CopyRepository    $copyRepo,
        private readonly EditionRepository $editionRepo,
    ) {}

    /**
     * Taux de rotation global du réseau : (Nombre total de prêts) / (Nombre total d'exemplaires).
     */
    public function getRotationRate(): float
    {
        $totalLoans  = $this->loanRepo->countTotalLoans();
        $totalCopies = $this->copyRepo->countTotalCopies();

        if ($totalCopies === 0) {
            return 0.0;
        }

        return round($totalLoans / $totalCopies, 2);
    }

    /**
     * Top des éditions les plus empruntées.
     *
     * @return array<int, array{edition: Edition, loanCount: int}>
     */
    public function getTopBorrowedEditions(int $limit = 10): array
    {
        return $this->editionRepo->getTopBorrowedEditions($limit);
    }

    /**
     * Documents jamais empruntés (dormants).
     *
     * @return Edition[]
     */
    public function getDormantEditions(int $limit = 50): array
    {
        return $this->editionRepo->findDormantEditions($limit);
    }

    /**
     * Synthèse globale des indicateurs clés.
     *
     * @return array{totalLoans: int, totalCopies: int, rotationRate: float, topBorrowed: array, dormant: array}
     */
    public function getDashboardData(): array
    {
        $totalLoans   = $this->loanRepo->countTotalLoans();
        $totalCopies  = $this->copyRepo->countTotalCopies();
        $rotationRate = $totalCopies > 0 ? round($totalLoans / $totalCopies, 2) : 0.0;
        $topBorrowed  = $this->getTopBorrowedEditions(10);
        $dormant      = $this->getDormantEditions(50);

        return [
            'totalLoans'   => $totalLoans,
            'totalCopies'  => $totalCopies,
            'rotationRate' => $rotationRate,
            'topBorrowed'  => $topBorrowed,
            'dormant'      => $dormant,
        ];
    }
}
