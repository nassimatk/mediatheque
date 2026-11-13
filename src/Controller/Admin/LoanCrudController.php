<?php

namespace App\Controller\Admin;

use App\Entity\Loan;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class LoanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Loan::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('copy', 'Exemplaire');
        yield AssociationField::new('member', 'Adhérent');
        yield DateTimeField::new('borrowedAt', 'Emprunté le');
        yield DateTimeField::new('dueAt', 'À rendre le');
        yield DateTimeField::new('closedAt', 'Rendu le');
        yield IntegerField::new('renewalCount', 'Prolongations');
    }
}
