<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MediaItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for MediaItem entity.
 *
 * @extends ServiceEntityRepository<MediaItem>
 */
class MediaItemRepository extends ServiceEntityRepository
{
    /** @var string Doctrine result-cache ID for the published gallery query. */
    private const PUBLISHED_CACHE_ID = 'media_published';

    /** @var int Result-cache lifetime in seconds (1 hour). */
    private const PUBLISHED_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaItem::class);
    }

    /**
     * Finds all published media items ordered by sort order.
     *
     * The result is cached via Doctrine's result cache (see config: the
     * `doctrine.result_cache_pool` is wired in prod). The cache is invalidated
     * on every write through {@see invalidatePublishedCache()} so the admin
     * always sees fresh data after a reorder/save/delete.
     *
     * @return list<MediaItem> List of published media items
     */
    public function findPublishedOrdered(): array
    {
        /** @var list<MediaItem> */
        return $this->createQueryBuilder('m')
            ->andWhere('m.published = true')
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->enableResultCache(self::PUBLISHED_CACHE_TTL, self::PUBLISHED_CACHE_ID)
            ->getResult();
    }

    /**
     * Applies new sort orders to media items and flushes once.
     *
     * Unknown IDs are skipped silently. Only existing items are updated.
     *
     * @param array<int, int> $idToPosition Map of media item ID to its new sort order
     * @return void
     */
    public function updateSortOrders(array $idToPosition): void
    {
        $em = $this->getEntityManager();

        foreach ($idToPosition as $id => $sortOrder) {
            $mediaItem = $this->find($id);
            if ($mediaItem !== null) {
                $mediaItem->setSortOrder($sortOrder);
            }
        }

        $em->flush();
        $this->invalidatePublishedCache();
    }

    /**
     * Saves a media item to the database.
     *
     * @param MediaItem $entity The media item to save
     * @param bool $flush Whether to flush the entity manager
     * @return void
     */
    public function save(MediaItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
            $this->invalidatePublishedCache();
        }
    }

    /**
     * Removes a media item from the database.
     *
     * @param MediaItem $entity The media item to remove
     * @param bool $flush Whether to flush the entity manager
     * @return void
     */
    public function remove(MediaItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
            $this->invalidatePublishedCache();
        }
    }

    /**
     * Invalidates the cached published-gallery result so the next read is fresh.
     *
     * No-op when no result cache pool is configured (e.g. dev/test).
     *
     * @return void
     */
    private function invalidatePublishedCache(): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $cache?->deleteItem(self::PUBLISHED_CACHE_ID);
    }
}
