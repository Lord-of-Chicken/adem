<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the media gallery page.
 */
final class GalleryController extends AbstractController
{
    /**
     * Displays the media gallery with published media items.
     *
     * @param MediaItemRepository $mediaItemRepository The media item repository
     * @param TranslatorInterface $translator The translator service
     * @return Response The gallery page response
     */
    #[Route('/galerie', name: 'app_gallery')]
    public function index(
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator,
    ): Response {
        return $this->render('gallery/index.html.twig', [
            'nav_links' => [
                ['label' => $translator->trans('nav.offres'), 'href' => $this->generateUrl('app_home') . '#offres'],
                ['label' => $translator->trans('nav.medias'), 'href' => $this->generateUrl('app_gallery')],
            ],
            'page' => [
                'title' => $translator->trans('home.gallery_title'),
                'empty' => $translator->trans('gallery.empty'),
            ],
            'medias_intro' => $translator->trans('gallery.meta_description'),
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}
