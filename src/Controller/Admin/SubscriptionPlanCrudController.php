<?php

namespace App\Controller\Admin;

use App\Entity\SubscriptionPlan;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SubscriptionPlanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SubscriptionPlan::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom de la formule');
        yield IntegerField::new('maxLoans', 'Prêts simultanés max');
        yield IntegerField::new('loanDays', 'Durée de prêt (jours)');
        yield IntegerField::new('maxRenewals', 'Renouvellements max');
        yield IntegerField::new('penaltyCentsPerDay', 'Pénalité par jour (centimes)');
    }
}
