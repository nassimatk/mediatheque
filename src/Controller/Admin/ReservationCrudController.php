<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

/**
 * Consultation des réservations en back-office.
 *
 * Conforme à D-002 : la création, la modification et la suppression manuelles sont désactivées
 * pour TOUS les utilisateurs (y compris ADMIN). Les réservations sont gérées exclusivement via ReservationService
 * (file FIFO, transit inter-sites, fenêtres de mise de côté de 5 jours et expirations).
 */
class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('member', 'Adhérent');
        yield AssociationField::new('edition', 'Édition');
        yield AssociationField::new('pickupSite', 'Site de retrait');
        yield AssociationField::new('copy', 'Exemplaire mis de côté');
        yield DateTimeField::new('createdAt', 'Réservé le');
        yield DateTimeField::new('holdUntil', 'Mis de côté jusqu\'au');
    }
}
