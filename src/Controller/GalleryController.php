<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Repository\MediaItemRepository;
use App\Repository\SiteSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GalleryController extends AbstractController
{
    #[Route('/galerie', name: 'app_gallery')]
    public function index(
        CartService $cart,
        MediaItemRepository $mediaItemRepository,
        SiteSettingRepository $siteSettingRepository,
    ): Response {
        $mediasIntro = $siteSettingRepository->get('section.medias.intro')
            ?: 'Quelques images de la ruelle — le lieu du projet, tel qu\'on le vit au quotidien.';

        return $this->render('gallery/index.html.twig', [
            'nav_links' => [
                ['label' => 'Offres', 'href' => $this->generateUrl('app_home') . '#offres'],
                ['label' => 'Médias', 'href' => $this->generateUrl('app_gallery')],
            ],
            'page' => [
                'title' => 'Galerie',
                'intro' => $mediasIntro,
                'empty' => 'Aucun média disponible pour le moment.',
            ],
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
            'cart_line_count' => $cart->countLines(),
            'footer_line' => $siteSettingRepository->get('brand.title') ?: 'La Ruelle d\'Adem',
        ]);
    }
}
