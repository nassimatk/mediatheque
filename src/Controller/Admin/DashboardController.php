<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(WorkCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Médiathèque — Gestion du réseau');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(WorkCrudController::class, 'Œuvres', 'fa fa-book-open');
        yield MenuItem::linkTo(EditionCrudController::class, 'Éditions', 'fa fa-bookmark');
        yield MenuItem::linkTo(CopyCrudController::class, 'Exemplaires', 'fa fa-barcode');
        yield MenuItem::linkTo(ContributorCrudController::class, 'Contributeurs', 'fa fa-users');

        yield MenuItem::section('Adhérents');
        yield MenuItem::linkTo(MemberCrudController::class, 'Adhérents', 'fa fa-id-card');
        yield MenuItem::linkTo(SubscriptionPlanCrudController::class, 'Formules d\'abonnement', 'fa fa-tags');

        yield MenuItem::section('Circulation');
        yield MenuItem::linkTo(LoanCrudController::class, 'Prêts', 'fa fa-exchange-alt');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Réservations', 'fa fa-clock');
        yield MenuItem::linkTo(PenaltyCrudController::class, 'Pénalités', 'fa fa-exclamation-triangle');

        yield MenuItem::section('Réseau');
        yield MenuItem::linkTo(SiteCrudController::class, 'Sites', 'fa fa-building');
    }
}
