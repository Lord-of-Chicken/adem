<?php

namespace App\Controller;

use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GalleryController extends AbstractController
{
    #[Route('/galerie', name: 'app_gallery')]
    public function index(
        MediaItemRepository $mediaItemRepository,
    ): Response {
        return $this->render('gallery/index.html.twig', [
            'nav_links' => [
                ['label' => 'Offres', 'href' => $this->generateUrl('app_home') . '#offres'],
                ['label' => 'Médias', 'href' => $this->generateUrl('app_gallery')],
            ],
            'page' => [
                'title' => 'Galerie',
                'empty' => 'Aucun média disponible pour le moment.',
            ],
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}
