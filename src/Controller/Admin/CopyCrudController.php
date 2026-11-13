<?php

namespace App\Controller\Admin;

use App\Entity\Copy;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CopyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Copy::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('barcode', 'Code-barres');
        yield AssociationField::new('edition', 'Édition');
        yield AssociationField::new('site', 'Site actuel');
        yield AssociationField::new('destinationSite', 'Site de destination (si transit)');
    }
}
