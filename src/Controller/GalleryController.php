<?php

namespace App\Controller;

use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GalleryController extends AbstractController
{
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
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}
