<?php

namespace App\Controller\Admin;

use App\Service\Statistics\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class StatsController extends AbstractController
{
    #[Route('/admin/statistiques', name: 'admin_stats')]
    public function index(StatsService $statsService): Response
    {
        $data = $statsService->getDashboardData();

        return $this->render('admin/stats/index.html.twig', [
            'stats' => $data,
        ]);
    }
}
