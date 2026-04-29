<?php

namespace App\Controller;

use App\Entity\MediaItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageEditController extends AbstractController
{
    #[Route('/admin/media-item/{id}/edit-image', name: 'media_item_edit_image', methods: ['GET', 'POST'])]
    public function editImage(MediaItem $mediaItem): Response
    {
        return $this->render('admin/image_edit.html.twig', [
            'mediaItem' => $mediaItem,
        ]);
    }
}
