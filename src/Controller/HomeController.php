<?php

namespace App\Controller;

use App\Participation\ParticipationCatalog;
use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ParticipationCatalog $catalog,
        MediaItemRepository $mediaItemRepository,
        TranslatorInterface $translator,
    ): Response {
        $allTiers = $catalog->all();
        $tiersStandard = array_values(array_filter($allTiers, fn ($t) => $t['group'] === 'standard'));
        $tiersVip = array_values(array_filter($allTiers, fn ($t) => $t['group'] === 'vip'));

        // Translate tier titles and details using keys from YAML
        foreach ($tiersStandard as &$tier) {
            if (isset($tier['title_key'])) {
                $tier['title'] = $translator->trans($tier['title_key']);
            }
            if (isset($tier['detail_key'])) {
                $tier['detail'] = $translator->trans($tier['detail_key']);
            }
            if (isset($tier['price_key'])) {
                $tier['price'] = $translator->trans($tier['price_key']);
            }
            if (isset($tier['price_suffix_key'])) {
                $tier['price_suffix'] = $translator->trans($tier['price_suffix_key']);
            }
        }
        unset($tier);

        foreach ($tiersVip as &$tier) {
            if (isset($tier['title_key'])) {
                $tier['title'] = $translator->trans($tier['title_key']);
            }
            if (isset($tier['detail_key'])) {
                $tier['detail'] = $translator->trans($tier['detail_key']);
            }
        }
        unset($tier);

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
