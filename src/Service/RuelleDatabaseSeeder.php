<?php

namespace App\Service;

use App\Entity\MediaItem;
use App\Entity\ParticipationTier;
use App\Entity\SiteSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RuelleDatabaseSeeder
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function seed(EntityManagerInterface $em): void
    {
        $this->seedParticipationTiers($em);
        $this->seedSiteSettings($em);
        $this->seedMediaFromRuelleFolder($em);
        $em->flush();
    }

    private function seedParticipationTiers(EntityManagerInterface $em): void
    {
        foreach ($this->tierDefinitions() as $def) {
            $tier = $em->find(ParticipationTier::class, $def['id']);
            if (!$tier instanceof ParticipationTier) {
                $tier = new ParticipationTier();
                $tier->setId($def['id']);
                $em->persist($tier);
            }
            $tier->setTitle($def['title']);
            $tier->setDetail($def['detail']);
            $tier->setPriceLabel($def['price_label']);
            $tier->setPriceUnit($def['price_unit']);
            $tier->setPriceSuffix($def['price_suffix']);
            $tier->setUnitPriceEur($def['unit_price_eur']);
            $tier->setPricedPerUnit($def['priced_per_unit']);
            $tier->setMinQty($def['min_qty']);
            $tier->setMaxQty($def['max_qty']);
            $tier->setTierGroup($def['tier_group']);
            $tier->setDonorField($def['donor_field']);
            $tier->setSortOrder($def['sort_order']);
            $tier->setActive(true);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tierDefinitions(): array
    {
        return [
            [
                'id' => 'begonia_unit',
                'title' => '1 bégonia ou plus',
                'detail' => 'Terreau et jardinière inclus.',
                'price_label' => '1',
                'price_unit' => '€',
                'price_suffix' => '/ pièce',
                'unit_price_eur' => '1.00',
                'priced_per_unit' => true,
                'min_qty' => 1,
                'max_qty' => 500,
                'tier_group' => 'standard',
                'donor_field' => false,
                'sort_order' => 10,
            ],
            [
                'id' => 'pack_3',
                'title' => '3 bégonias + jardinière',
                'detail' => 'Ensemble prêt à planter.',
                'price_label' => '6',
                'price_unit' => '€',
                'price_suffix' => null,
                'unit_price_eur' => '6.00',
                'priced_per_unit' => false,
                'min_qty' => 1,
                'max_qty' => 1,
                'tier_group' => 'standard',
                'donor_field' => false,
                'sort_order' => 20,
            ],
            [
                'id' => 'vip_20',
                'title' => '3 bégonias + jardinière',
                'detail' => 'Ton nom sera affiché en remerciement.',
                'price_label' => '20',
                'price_unit' => '€',
                'price_suffix' => null,
                'unit_price_eur' => '20.00',
                'priced_per_unit' => false,
                'min_qty' => 1,
                'max_qty' => 1,
                'tier_group' => 'vip',
                'donor_field' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 'vip_50',
                'title' => '1 palette + 12 bégonias + 4 jardinières zinc',
                'detail' => 'Soutien visible pour le projet.',
                'price_label' => '50',
                'price_unit' => '€',
                'price_suffix' => null,
                'unit_price_eur' => '50.00',
                'priced_per_unit' => false,
                'min_qty' => 1,
                'max_qty' => 1,
                'tier_group' => 'vip',
                'donor_field' => true,
                'sort_order' => 20,
            ],
        ];
    }

    private function seedSiteSettings(EntityManagerInterface $em): void
    {
        $defaults = [
            'brand.title' => 'La Ruelle d’Adem',
            'brand.tagline' => 'Fait une fleur à \nLa Ruelle d’Adem',
            'brand.logo_asset' => 'img/Panneau/IMG_0197.png',
            'section.medias.intro' => 'Quelques images de la ruelle — le lieu du projet, tel qu’on le vit au quotidien.',
        ];

        foreach ($defaults as $key => $value) {
            $row = $em->find(SiteSetting::class, $key);
            if (!$row instanceof SiteSetting) {
                $row = new SiteSetting();
                $row->setSettingKey($key);
                $em->persist($row);
            }
            $row->setValue($value);
        }
    }

    private function seedMediaFromRuelleFolder(EntityManagerInterface $em): void
    {
        $dir = $this->projectDir.'/assets/img/ruelle';
        if (!is_dir($dir)) {
            return;
        }

        $paths = [];
        foreach (glob($dir.'/*.{jpeg,jpg,png,webp,gif}', \GLOB_BRACE) ?: [] as $fullPath) {
            $base = basename((string) $fullPath);
            $paths[] = 'img/ruelle/'.$base;
        }
        sort($paths);

        $order = 0;
        foreach ($paths as $assetPath) {
            $existing = $em->createQueryBuilder()
                ->select('m')
                ->from(MediaItem::class, 'm')
                ->andWhere('m.assetPath = :p')
                ->setParameter('p', $assetPath)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if ($existing instanceof MediaItem) {
                $existing->setSortOrder($order);
                $existing->setPublished(true);
                if (null === $existing->getAlt()) {
                    $existing->setAlt('La Ruelle d’Adem');
                }
                ++$order;

                continue;
            }

            $m = new MediaItem();
            $m->setAssetPath($assetPath);
            $m->setTitle(pathinfo($assetPath, \PATHINFO_FILENAME));
            $m->setAlt('La Ruelle d’Adem');
            $m->setSortOrder($order);
            $m->setPublished(true);
            $em->persist($m);
            ++$order;
        }
    }
}
