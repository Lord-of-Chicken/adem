<?php

namespace App\Controller;

use App\Participation\ParticipationCatalog;
use App\Repository\MediaItemRepository;
use App\Repository\SiteSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ParticipationCatalog $catalog,
        MediaItemRepository $mediaItemRepository,
        SiteSettingRepository $siteSettingRepository,
    ): Response {
        $mediasIntro = $siteSettingRepository->get('section.medias.intro')
            ?: 'Quelques images de la ruelle — le lieu du projet, tel qu’on le vit au quotidien.';

        return $this->render('home/index.html.twig', [
            'hero' => [
                'eyebrow' => 'Flower Power',
                'title' => 'Crowdfunding',
                'lead' => 'Le crowdfunding, ou « financement participatif », est un mécanisme qui permet de lever des fonds auprès du grand public — c’est-à-dire auprès de toi.',
            ],
            'nav_links' => [
                ['label' => 'Médias', 'href' => '#medias'],
            ],
            'event_teaser' => [
                'label' => 'Expo-happening Flower Power 2027',
                'title_attr' => 'À venir',
            ],
            'cta_newsletter' => [
                'href' => '#newsletter',
                'label' => '> Recevoir des notifications et invitations (mailing list)',
            ],
            'sections' => [
                'offres' => [
                    'id' => 'offres',
                    'title' => 'Offres de soutien',
                    'intro' => 'Choisis une formule simple ou une formule V.I.P. avec mention de ton nom.',
                ],
                'vip' => [
                    'id' => 'vip',
                    'title' => 'Formules V.I.P.',
                    'subtitle' => 'Avec indication du nom du donateur',
                ],
                'don_libre' => [
                    'id' => 'don-libre',
                    'title' => 'Don libre',
                    'intro' => 'Avec ou sans indication du nom du donateur.',
                ],
                'contact' => [
                    'id' => 'contact',
                    'title' => 'Proposition, don, sponsoring & suggestion',
                    'intro' => 'Quelque chose à proposer ou à suggérer à Adem ?',
                ],
                'newsletter' => [
                    'id' => 'newsletter',
                    'title' => 'Notifications & invitations',
                    'intro' => 'Inscris-toi pour recevoir les nouvelles du projet et les invitations aux événements.',
                ],
                'medias' => [
                    'id' => 'medias',
                    'title' => 'Médias',
                    'intro' => $mediasIntro,
                ],
            ],
            'tiers_standard' => $catalog->standardForHome(),
            'tiers_vip' => $catalog->vipForHome(),
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
            'footer_line' => 'La Ruelle d’Adem — Flower Power',
        ]);
    }
}
