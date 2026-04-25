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

class MediaItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MediaItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Média')
            ->setEntityLabelInPlural('Médias')
            ->setDefaultSort(['sortOrder' => 'ASC'])
            ->setSearchFields(['title', 'alt'])
            ->overrideTemplate('crud/index', 'easy_admin/media_item_index.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title', 'Titre'),
            ImageField::new('assetPath', 'Image')
                ->setBasePath('/')
                ->setUploadDir('assets/img/ruelle')
                ->setUploadedFileNamePattern('[name]-[uuid].[extension]')
                ->setRequired(false),
            TextField::new('alt', 'Texte alternatif'),
            IntegerField::new('sortOrder', 'Ordre')->hideOnIndex(),
            BooleanField::new('published', 'Publié'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $editImageAction = Action::new('editImage', 'Éditer image', 'fa fa-image')
            ->linkToUrl(function(MediaItem $entity) {
                return 'javascript:openImageEditor(' . $entity->getId() . ', \'' . $entity->getAssetPath() . '\')';
            })
            ->addCssClass('btn btn-info')
            ->displayIf(function (MediaItem $entity) {
                return $entity->getAssetPath() !== null;
            });

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $editImageAction);
    }
}
