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
                'title' => '',
                'lead' => '',
            ],
            'nav_links' => [
                ['label' => 'Offres', 'href' => '#offres'],
                ['label' => 'Médias', 'href' => '#medias'],
            ],
            'medias_intro' => 'Quelques images de la ruelle — le lieu du projet, tel qu\'on le vit au quotidien.',
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
                    'intro' => 'TU VEUX CONNAITRE LE NOMBRE DE FLEURS, DE JARDINIERES ET DE PALETTES OFFERTES?<br>Savoir si un événement festif, récréatif ou éducatif se déroule dans la ruelle.',
                ],
            ],
            'tiers_standard' => $catalog->standardForHome(),
            'tiers_vip' => $catalog->vipForHome(),
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}