<?php

namespace App\Controller\Admin;

use App\Entity\Edition;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class EditionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Edition::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('work', 'Œuvre');
        yield TextField::new('title', 'Titre de l\'édition');
        yield TextField::new('isbn', 'ISBN');
        yield TextField::new('publisher', 'Éditeur');
        yield IntegerField::new('publicationYear', 'Année');
        yield UrlField::new('coverUrl', 'Couverture (URL)')->hideOnIndex();
        yield AssociationField::new('copies', 'Exemplaires')->hideOnForm();
    }
}
