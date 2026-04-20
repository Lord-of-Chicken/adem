<?php

namespace App\Controller\Admin;

use App\Entity\MediaItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class MediaItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MediaItem::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Titre'),
            ImageField::new('assetPath', 'Image')
                ->setBasePath('/assets/img/ruelle')
                ->setUploadDir('public/assets/img/ruelle')
                ->setUploadedFileNamePattern('[name]-[uuid].[extension]')
                ->setRequired(false),
            TextField::new('alt', 'Texte alternatif'),
            IntegerField::new('sortOrder', 'Ordre')->hideOnIndex(),
            BooleanField::new('published', 'Publié'),
        ];
    }
}
