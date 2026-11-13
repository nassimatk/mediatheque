<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
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
