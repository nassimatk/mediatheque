<?php

namespace App\Controller\Admin;

use App\Entity\Loan;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

/**
 * Consultation des prêts en back-office.
 *
 * Conforme à D-002 : la création, la modification et la suppression manuelles sont désactivées
 * pour TOUS les utilisateurs (y compris ADMIN). Les prêts sont gérés exclusivement via LoanService
 * (verrous pessimistes, calculs de dates, gestion des quotas et transitions d'état).
 */
class LoanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Loan::class;
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
        yield AssociationField::new('copy', 'Exemplaire');
        yield AssociationField::new('member', 'Adhérent');
        yield DateTimeField::new('borrowedAt', 'Emprunté le');
        yield DateTimeField::new('dueAt', 'À rendre le');
        yield DateTimeField::new('closedAt', 'Rendu le');
        yield IntegerField::new('renewalCount', 'Prolongations');
    }
}
