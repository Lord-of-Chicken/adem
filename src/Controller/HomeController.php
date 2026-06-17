<?php

declare(strict_types=1);

namespace App\Controller;

use App\Participation\ParticipationCatalog;
use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the home page display with participation tiers and media items.
 */
final class HomeController extends AbstractController
{
    /**
     * Displays the home page with participation tiers and media gallery.
     *
     * @param ParticipationCatalog $catalog The participation catalog
     * @param MediaItemRepository $mediaItemRepository The media item repository
     * @param TranslatorInterface $translator The translator service
     * @return Response The home page response
     */
    #[Route('/', name: 'app_home')]
    public function index(
        ParticipationCatalog $catalog,
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator,
    ): Response {
        $translatedTiers = $catalog->getTranslatedTiers($translator);
        $tiersStandard = $translatedTiers['standard'];
        $tiersVip = $translatedTiers['vip'];

        return $this->render('home/index.html.twig', [
            'hero' => [
                'title' => '',
                'lead' => '',
            ],
            'navLinks' => [
                ['label' => $translator->trans('nav.offres'), 'href' => '#offres'],
                ['label' => $translator->trans('nav.medias'), 'href' => '#medias'],
            ],
            'medias_intro' => $translator->trans('home.medias_intro'),
            'sections' => [
                'offres' => [
                    'id' => 'offres',
                    'title' => $translator->trans('home.offres_title'),
                    'intro' => '',
                ],
                'vip' => [
                    'id' => 'vip',
                    'title' => $translator->trans('home.vip_title'),
                    'subtitle' => $translator->trans('home.vip_subtitle'),
                ],
                'don_libre' => [
                    'id' => 'don-libre',
                    'title' => $translator->trans('home.don_libre_title'),
                    'intro' => $translator->trans('home.don_libre_intro'),
                ],
                'medias' => [
                    'id' => 'medias',
                    'title' => $translator->trans('home.gallery_title'),
                ],
                'newsletter' => [
                    'id' => 'newsletter',
                    'title' => $translator->trans('home.newsletter_title'),
                    'intro' => $translator->trans('home.newsletter_intro'),
                ],
            ],
            'tiers_standard' => $tiersStandard,
            'tiers_vip' => $tiersVip,
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}
