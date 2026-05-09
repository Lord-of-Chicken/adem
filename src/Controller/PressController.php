<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PressController extends AbstractController
{
    #[Route('/presse', name: 'app_press')]
    public function index(TranslatorInterface $translator): Response
    {
        $pressItems = [
            // Reportages Vidéo & TV
            [
                'category' => 'video',
                'source' => 'BX1',
                'title' => 'Uccle : Adem, 10 ans, a redonné vie à une ruelle du quartier',
                'url' => 'https://bx1.be/categories/news/uccle-adem-10-ans-a-redonne-vie-a-une-ruelle-du-quartier/',
                'type' => 'tv',
                'image' => null,
            ],
            [
                'category' => 'video',
                'source' => 'BRUZZ',
                'title' => 'Adem (10 ans) transforme un sentier malfamé d\'Uccle en ruelle des roses',
                'url' => 'https://www.bruzz.be/fr/video/adem-10-ans-transforme-un-sentier-mal-fame-duccle-en-ruelle-des-roses',
                'type' => 'tv',
                'image' => 'img/presses/A bruzz.jpg',
            ],
            [
                'category' => 'video',
                'source' => 'RTBF Info',
                'title' => 'Le portrait d\'Adem, le petit magicien d\'Uccle',
                'url' => 'https://www.facebook.com/rtbfinfo/videos/adem-11-ans-a-transformé-une-ruelle-duccle-en-véritable-paradis/584226160249265/',
                'type' => 'tv',
                'image' => null,
            ],
            // Articles de la Presse Écrite
            [
                'category' => 'written',
                'source' => 'RTBF.be',
                'title' => 'Adem, 11 ans, a transformé une ruelle d\'Uccle en véritable paradis pour les oiseaux et les passants',
                'url' => 'https://www.rtbf.be/article/adem-11-ans-a-transforme-une-ruelle-d-uccle-en-veritable-paradis-pour-les-oiseaux-et-les-passants-11110003',
                'type' => 'article',
                'image' => null,
            ],
            [
                'category' => 'written',
                'source' => 'La DH',
                'title' => 'Bruxellois de l\'année : Adem Schol, le magicien du Chemin des Roses',
                'url' => 'https://www.dhnet.be/regions/bruxelles/uccle/2023/01/24/bruxellois-de-lannee-adem-schol-le-magicien-du-chemin-des-roses-V2YQ6L5WJNG6RIZ5K6O2H7C7QU/',
                'type' => 'article',
                'image' => null,
            ],
            [
                'category' => 'written',
                'source' => 'Le Soir',
                'title' => 'Adem, le petit prince de la ruelle d\'Uccle (Lauréat Bruxellois de l\'année)',
                'url' => 'https://www.lesoir.be/494191/article/2023-02-09/bruxellois-de-lannee-les-laureats-sont-connus',
                'type' => 'article',
                'image' => null,
            ],
            [
                'category' => 'written',
                'source' => 'Radio Contact',
                'title' => 'Adem, 10 ans, transforme une ruelle glauque d\'Uccle en havre de paix',
                'url' => 'https://www.radiocontact.be/news/insolite/adem-10-ans-transforme-une-ruelle-glauque-d-uccle-en-veritable-havre-de-paix-87431.htm',
                'type' => 'article',
                'image' => null,
            ],
            [
                'category' => 'written',
                'source' => 'Le Vif',
                'title' => 'La ruelle d\'Adem (Chronique)',
                'url' => 'https://www.levif.be/opinions/chroniques/la-ruelle-dadem/',
                'type' => 'article',
                'image' => null,
            ],
            [
                'category' => 'written',
                'source' => 'Sud Info – La Capitale',
                'title' => 'Ruelle d\'Adem',
                'url' => null,
                'type' => 'article',
                'image' => 'img/presses/Ruelle d\'Adem -SudInfo - La Capitale. 11.09.2025.jpg',
            ],
            [
                'category' => 'written',
                'source' => 'Sud Presse',
                'title' => 'Article Sud Presse',
                'url' => null,
                'type' => 'article',
                'image' => 'img/presses/Sud Presse 2025-12-11 141215.png',
            ],
            [
                'category' => 'written',
                'source' => 'HLN',
                'title' => 'Article HLN',
                'url' => null,
                'type' => 'article',
                'image' => 'img/presses/HLN.jpg',
            ],
            [
                'category' => 'written',
                'source' => 'Presse flamande',
                'title' => 'Article presse flamande',
                'url' => null,
                'type' => 'article',
                'image' => 'img/presses/Presse flamande.jpg',
            ],
            [
                'category' => 'written',
                'source' => 'BXL FM',
                'title' => 'Interview radio BXL FM',
                'url' => null,
                'type' => 'article',
                'image' => 'img/presses/Bxl FM.jpg',
            ],
            // Publications Officielles et Communautaires
            [
                'category' => 'official',
                'source' => 'Wolvendael (Commune d\'Uccle)',
                'title' => 'Portrait de citoyen : Adem Schol (numéro 685)',
                'url' => 'https://www.uccle.be/fr/vie-communale/wolvendael',
                'type' => 'official',
                'image' => 'img/presses/Wolvendael magazine.JPG',
            ],
            [
                'category' => 'official',
                'source' => 'Ucclensia',
                'title' => 'Article Ucclensia',
                'url' => null,
                'type' => 'official',
                'image' => 'img/presses/Ucclensia.jpg',
            ],
            [
                'category' => 'official',
                'source' => 'Facebook',
                'title' => 'Le Chemin des Roses / De Rozenweg',
                'url' => 'https://www.facebook.com/chemindesrosesuccle/',
                'type' => 'official',
                'image' => null,
            ],
        ];

        return $this->render('press/index.html.twig', [
            'page' => [
                'title' => $translator->trans('press.title'),
                'intro' => $translator->trans('press.intro'),
            ],
            'categories' => [
                'video' => [
                    'title' => $translator->trans('press.category_video'),
                    'items' => array_filter($pressItems, fn($item) => $item['category'] === 'video'),
                ],
                'written' => [
                    'title' => $translator->trans('press.category_written'),
                    'items' => array_filter($pressItems, fn($item) => $item['category'] === 'written'),
                ],
                'official' => [
                    'title' => $translator->trans('press.category_official'),
                    'items' => array_filter($pressItems, fn($item) => $item['category'] === 'official'),
                ],
            ],
        ]);
    }
}
