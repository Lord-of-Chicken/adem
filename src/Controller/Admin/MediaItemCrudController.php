<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MediaItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @extends AbstractCrudController<MediaItem>
 */
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
            ImageField::new('assetPath', 'Image')
                ->setBasePath('/')
                ->onlyOnIndex(),
            TextField::new('title', 'Titre'),
            TextField::new('file', 'Image')
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => $pageName === Crud::PAGE_NEW,
                    'attr' => [
                        'accept' => 'image/*',
                        'class' => 'form-control',
                    ]
                ])
                ->onlyOnForms(),
            TextField::new('assetPath', 'Chemin de l\'image')->hideOnForm(),
            TextField::new('alt', 'Texte alternatif'),
            IntegerField::new('sortOrder', 'Ordre')->hideOnIndex(),
            BooleanField::new('published', 'Publié'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $editImageAction = Action::new('editImage', 'Éditer image', 'fa fa-crop')
            ->linkToUrl(function(MediaItem $entity) {
                return $this->generateUrl('media_item_edit_image', ['id' => $entity->getId()]);
            })
            ->addCssClass('btn btn-info')
            ->displayIf(function (MediaItem $entity) {
                return $entity->getAssetPath() !== null;
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $editImageAction)
            ->add(Crud::PAGE_EDIT, $editImageAction);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $request = $this->getContext()?->getRequest();
        $files = $request?->files->get('MediaItem');
        $file = is_array($files) ? ($files['file'] ?? null) : null;

        if ($file instanceof UploadedFile && $entityInstance instanceof MediaItem) {
            $fileName = uniqid('', true) . '.' . $file->guessExtension();

            $destination = $this->getParameter('kernel.project_dir') . '/public/img/ruelle/';
            $file->move($destination, $fileName);

            $entityInstance->setAssetPath('img/ruelle/' . $fileName);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
