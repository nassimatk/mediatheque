<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

/**
 * Trait garantissant que le rôle ROLE_LIBRARIAN accède au back-office en lecture seule (consultation et détail),
 * tandis que les opérations de création, modification et suppression sont réservées à ROLE_ADMIN.
 */
trait ReadOnlyForLibrarianTrait
{
    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        if (!$this->isGranted('ROLE_ADMIN')) {
            return $actions
                ->disable(Action::NEW, Action::EDIT, Action::DELETE)
                ->add(Crud::PAGE_INDEX, Action::DETAIL);
        }

        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
