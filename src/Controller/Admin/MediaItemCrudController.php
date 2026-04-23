<?php

namespace App\Controller\Admin;

use App\Entity\MediaItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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
                ->setBasePath('/assets')
                ->setUploadDir('public/assets')
                ->setUploadedFileNamePattern('[name]-[uuid].[extension]')
                ->setRequired(false),
            TextField::new('alt', 'Texte alternatif'),
            IntegerField::new('sortOrder', 'Ordre')->hideOnIndex(),
            BooleanField::new('published', 'Publié'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $editImageAction = Action::new('editImage', 'Éditer image', 'fa fa-edit')
            ->linkToUrl(function(MediaItem $entity) {
                return '/assets/' . $entity->getAssetPath();
            })
            ->setHtmlAttributes(['target' => '_blank'])
            ->addCssClass('btn btn-info')
            ->displayIf(function (MediaItem $entity) {
                return $entity->getAssetPath() !== null;
            });

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $editImageAction);
    }
}
