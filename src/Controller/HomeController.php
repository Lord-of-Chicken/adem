<?php

namespace App\Controller;

use App\Participation\ParticipationCatalog;
use App\Repository\MediaItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public const TIERS = [
        [
            'id' => 'begonia_unit',
            'title' => '1 bégonia ou plus',
            'detail' => 'Terreau et jardinière inclus.',
            'price' => '1',
            'price_unit' => '€',
            'price_suffix' => '/ pièce',
            'unit_price_eur' => 1.00,
            'priced_per_unit' => true,
            'min_qty' => 1,
            'max_qty' => 500,
            'group' => 'standard',
            'donor_field' => false,
        ],
        [
            'id' => 'pack_3',
            'title' => '3 bégonias + jardinière',
            'detail' => 'Ensemble prêt à planter.',
            'price' => '6',
            'price_unit' => '€',
            'price_suffix' => null,
            'unit_price_eur' => 6.00,
            'priced_per_unit' => false,
            'min_qty' => 1,
            'max_qty' => 500,
            'group' => 'standard',
            'donor_field' => false,
        ],
        [
            'id' => 'vip_20',
            'title' => '3 bégonias + jardinière',
            'detail' => 'Ton nom sera affiché en remerciement.',
            'price' => '20',
            'price_unit' => '€',
            'price_suffix' => null,
            'unit_price_eur' => 20.00,
            'priced_per_unit' => false,
            'min_qty' => 1,
            'max_qty' => 500,
            'group' => 'vip',
            'donor_field' => true,
        ],
        [
            'id' => 'vip_50',
            'title' => '1 palette + 12 bégonias + 4 jardinières zinc',
            'detail' => 'Soutien visible pour le projet.',
            'price' => '50',
            'price_unit' => '€',
            'price_suffix' => null,
            'unit_price_eur' => 50.00,
            'priced_per_unit' => false,
            'min_qty' => 1,
            'max_qty' => 500,
            'group' => 'vip',
            'donor_field' => true,
        ],
        [
            'id' => 'free_donation',
            'title' => 'Don Libre',
            'detail' => 'Soutien personnalisé au projet.',
            'price' => 'Libre',
            'price_unit' => '€',
            'price_suffix' => null,
            'unit_price_eur' => 0.00,
            'priced_per_unit' => false,
            'min_qty' => 1,
            'max_qty' => 1,
            'group' => 'standard',
            'donor_field' => true,
        ],
    ];

    #[Route('/', name: 'app_home')]
    public function index(
        ParticipationCatalog $catalog,
        MediaItemRepository $mediaItemRepository,
    ): Response {
        $allTiers = $catalog->all();
        $tiersStandard = array_values(array_filter($allTiers, fn ($t) => $t['group'] === 'standard'));
        $tiersVip = array_values(array_filter($allTiers, fn ($t) => $t['group'] === 'vip'));

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
                    'intro' => '',
                ],
                'vip' => [
                    'id' => 'vip',
                    'title' => 'Formules V.I.P.',
                    'subtitle' => 'Grâce à cette formule, tu peux personnaliser ta jardinière ou ta palette.  De quoi épater tes amis lorsque tu leur fera visiter la ruelle d\'Adem, ou lorsqu\'il y découvriront ton nom.',
                ],
                'don_libre' => [
                    'id' => 'don-libre',
                    'title' => 'Don libre',
                    'intro' => 'Tu peux aussi contribuer en offrant le montant de ton choix. Et, si telle est ton envie, nous pouvons afficher ton nom sur ce site.',
                ],
                'medias' => [
                    'id' => 'medias',
                    'title' => 'Galerie',
                ],
                'newsletter' => [
                    'id' => 'newsletter',
                    'title' => 'Restez informé',
                    'intro' => "TU VEUX CONNAITRE LE NOMBRE DE FLEURS, DE JARDINIERES ET DE PALETTES OFFERTES?\nSavoir si un événement festif, récréatif ou éducatif se déroule dans la ruelle.",
                ],
            ],
            'tiers_standard' => $tiersStandard,
            'tiers_vip' => $tiersVip,
            'media_items' => $mediaItemRepository->findPublishedOrdered(),
        ]);
    }
}