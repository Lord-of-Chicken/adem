<?php

namespace App\Controller;

use App\Cart\CartService;
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
        CartService $cart,
        ParticipationCatalog $catalog,
        MediaItemRepository $mediaItemRepository,
        SiteSettingRepository $siteSettingRepository,
    ): Response {
        $brandTagline = $siteSettingRepository->get('brand.tagline') ?: 'Fait une fleur à la Ruelle d\'Adem';
        $mediasIntro = $siteSettingRepository->get('section.medias.intro')
            ?: 'Quelques images de la ruelle — le lieu du projet, tel qu’on le vit au quotidien.';

        return $this->render('home/index.html.twig', [
            'hero' => [
                // Remplacement de \n par un espace (plus de <br>)
                'eyebrow' => str_replace('\n', ' ', $brandTagline), 
                'title' => 'Crowdfunding',
                'lead' => 'Le crowdfunding, ou « financement participatif », est un mécanisme qui permet de lever des fonds auprès du grand public — c\'est-à-dire auprès de toi.',
            ],
            'nav_links' => [
                ['label' => 'Offres', 'href' => '#offres'],
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
                    'intro' => 'Envie de contribuer différemment ? Contactez-nous pour un don libre.',
                ],
                'contact' => [
                    'id' => 'contact',
                    'title' => 'Proposition & Contact',
                    'intro' => 'Quelque chose à proposer ou à suggérer à Adem ?',
                ],
                'newsletter' => [
                    'id' => 'newsletter',
                    'title' => 'Notifications & invitations',
                    'intro' => 'Inscris-toi pour recevoir les nouvelles du projet.',
                ],
                'medias' => [
                    'id' => 'medias',
                    'title' => 'La Galerie',
                    'intro' => $mediasIntro,
                ],
            ],
            'tiers_standard' => $catalog->standardForHome(),
            'tiers_vip' => $catalog->vipForHome(),
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
            'cart_line_count' => $cart->countLines(),
            'footer_line' => $siteSettingRepository->get('brand.title') ?: 'La Ruelle d\'Adem',
        ]);
    }
}