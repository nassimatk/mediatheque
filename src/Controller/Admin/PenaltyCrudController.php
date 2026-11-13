<?php

namespace App\Controller\Admin;

use App\Entity\Penalty;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class PenaltyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Penalty::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('loan', 'Prêt concerné');
        yield IntegerField::new('amountCents', 'Montant (centimes de dirham)');
        yield DateTimeField::new('createdAt', 'Créée le');
        yield DateTimeField::new('paidAt', 'Payée le');
    }
}
