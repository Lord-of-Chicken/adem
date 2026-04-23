<?php

namespace App\Controller;

use App\Participation\ParticipationCatalog;
use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ParticipationCatalog $catalog,
        MediaItemRepository $mediaItemRepository,
    ): Response {
        return $this->render('home/index.html.twig', [
            'hero' => [
                'title' => ' Crowdfunding',
                'lead' => 'Le crowdfunding est un mécanisme qui permet de lever des fonds auprès du grand public — c\'est-à-dire auprès de toi.',
            ],
            'nav_links' => [
                ['label' => 'Offres', 'href' => '#offres'],
                ['label' => 'Médias', 'href' => '#medias'],
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
                    'intro' => 'Envie de contribuer différemment ? Fixez vous-même votre montant.',
                ],
                'medias' => [
                    'id' => 'medias',
                    'title' => 'Galerie',
                ],
                'newsletter' => [
                    'id' => 'newsletter',
                    'title' => 'Restez informé',
                    'intro' => 'Recevez des notifications et des invitations pour les événements à venir.',
                ],
            ],
            'tiers_standard' => $catalog->standardForHome(),
            'tiers_vip' => $catalog->vipForHome(),
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}