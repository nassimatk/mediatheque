<?php

namespace App\Controller\Admin;

use App\Entity\Penalty;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

/**
 * Consultation des pénalités en back-office.
 *
 * Conforme à D-002 : la création, la modification et la suppression manuelles sont désactivées
 * pour TOUS les utilisateurs (y compris ADMIN). Les pénalités sont générées automatiquement lors
 * des retours de prêts et réglées par le bibliothécaire au comptoir de circulation.
 */
class PenaltyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Penalty::class;
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
        yield AssociationField::new('loan', 'Prêt concerné');
        yield IntegerField::new('amountCents', 'Montant (centimes de dirham)');
        yield DateTimeField::new('createdAt', 'Créée le');
        yield DateTimeField::new('paidAt', 'Payée le');
    }
}
