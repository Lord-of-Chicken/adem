<?php

namespace App\Service;

use App\Entity\MediaItem;
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
        $this->seedMediaFromRuelleFolder($em);
        $em->flush();
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
