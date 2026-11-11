<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\MediaItem;
use App\Repository\MediaItemRepository;
use App\Tests\Support\BackendWebTestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class MediaReorderPerformanceTest extends BackendWebTestCase
{
    public function testColdBatchUsesOneSelectAndInvalidatesPublishedCache(): void
    {
        $configuration = $this->em->getConfiguration();
        $previous = $configuration->getResultCache();
        $configuration->setResultCache(new ArrayAdapter());
        try {
            $positions = [];
            for ($i = 0; $i < 30; ++$i) {
                $media = (new MediaItem())->setTitle('Performance fixture')->setAssetPath('img/fixture.png')
                    ->setSortOrder($i)->setPublished($i % 2 === 0);
                $this->em->persist($media);
                $this->em->flush();
                $id = $media->getId();
                self::assertNotNull($id);
                $positions[$id] = 30 - $i;
            }
            $repo = self::service(MediaItemRepository::class);
            $before = $repo->findPublishedOrdered();
            $expectedPublishedIds = array_reverse(array_map(static fn (MediaItem $item): ?int => $item->getId(), $before));
            $this->em->clear();
            $holder = static::getContainer()->get('doctrine.debug_data_holder');
            self::assertInstanceOf(DebugDataHolder::class, $holder);
            $holder->reset();
            // Unknown IDs retain the existing skip behavior.
            $repo->updateSortOrders($positions + [2147483647 => 0]);
            $selects = 0;
            foreach ($holder->getData() as $queries) {
                self::assertIsArray($queries);
                foreach ($queries as $query) {
                    self::assertIsArray($query);
                    self::assertArrayHasKey('sql', $query);
                    self::assertIsString($query['sql']);
                    if (str_starts_with(strtoupper($query['sql']), 'SELECT')) { ++$selects; }
                }
            }
            self::assertSame(1, $selects, 'Reordering must load the batch rather than each media separately.');
            $this->em->clear();
            foreach ($positions as $id => $position) {
                $fresh = $repo->find($id);
                self::assertNotNull($fresh);
                self::assertSame($position, $fresh->getSortOrder());
            }
            self::assertSame($expectedPublishedIds, array_map(static fn (MediaItem $item): ?int => $item->getId(), $repo->findPublishedOrdered()));
            self::assertSame(30, $repo->count([]));
        } finally {
            $configuration->setResultCache($previous ?? new ArrayAdapter());
        }
    }

    public function testReorderPreservesUnflushedManagedChangesAndOtherMedia(): void
    {
        $first = (new MediaItem())->setTitle('Original')->setSortOrder(1)->setPublished(false);
        $second = (new MediaItem())->setTitle('Untouched')->setSortOrder(2)->setPublished(true);
        $this->em->persist($first);
        $this->em->persist($second);
        $this->em->flush();
        $id = $first->getId();
        self::assertNotNull($id);
        $first->setTitle('Unflushed change');
        $repo = self::service(MediaItemRepository::class);
        $repo->updateSortOrders([$id => 7]);
        $this->em->refresh($first);
        $this->em->refresh($second);
        self::assertSame('Unflushed change', $first->getTitle());
        self::assertFalse($first->isPublished());
        self::assertSame(7, $first->getSortOrder());
        self::assertSame('Untouched', $second->getTitle());
        self::assertSame(2, $second->getSortOrder());
        self::assertTrue($second->isPublished());
    }

    public function testEmptyBatchRetainsFlushBehavior(): void
    {
        $pending = (new MediaItem())->setTitle('Pending')->setSortOrder(9);
        $this->em->persist($pending);
        self::service(MediaItemRepository::class)->updateSortOrders([]);
        self::assertNotNull($pending->getId());
        $this->em->refresh($pending);
        self::assertSame(9, $pending->getSortOrder());
    }
}
