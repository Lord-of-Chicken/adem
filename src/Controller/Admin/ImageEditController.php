<?php

namespace App\Controller\Admin;

use App\Entity\MediaItem;
use App\Form\ImageEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageEditController extends AbstractController
{
    #[Route('/admin/media-item/{id}/edit-image', name: 'media_item_edit_image', methods: ['GET', 'POST'])]
    public function editImage(Request $request, MediaItem $mediaItem, EntityManagerInterface $entityManager): Response
    {
        $publicUrl = '/assets/img/ruelle/' . basename($mediaItem->getAssetPath());
        
        $form = $this->createForm(ImageEditType::class, null, [
            'public_url' => $publicUrl,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                // Upload logic here
                $entityManager->flush();
                return $this->redirectToRoute('admin_media_item_index');
            }
        }

        return $this->render('form/image_edit.html.twig', [
            'form' => $form,
            'mediaItem' => $mediaItem,
        ]);
    }
}
