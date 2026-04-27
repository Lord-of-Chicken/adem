<?php

namespace App\Controller\Admin;

use App\Entity\MediaItem;
use App\Repository\MediaItemRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

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

    #[Route('/admin/media-item/reorder', name: 'app_admin_media_item_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        MediaItemRepository $mediaItemRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['orders']) || !is_array($data['orders'])) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data['orders'] as $orderData) {
            if (!isset($orderData['id']) || !isset($orderData['sortOrder'])) {
                continue;
            }

            $mediaItem = $mediaItemRepository->find($orderData['id']);
            if ($mediaItem) {
                $mediaItem->setSortOrder($orderData['sortOrder']);
            }
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
