<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MediaItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles media item image editing (admin only).
 */
#[IsGranted('ROLE_ADMIN')]
final class ImageEditController extends AbstractController
{
    /**
     * Displays the media item image editing page.
     *
     * @param MediaItem $mediaItem The media item to edit
     * @return Response The image edit page response
     */
    #[Route('/admin/media-item/{id}/edit-image', name: 'media_item_edit_image', methods: ['GET', 'POST'])]
    public function editImage(MediaItem $mediaItem): Response
    {
        return $this->render('admin/image_edit.html.twig', [
            'mediaItem' => $mediaItem,
        ]);
    }
}
