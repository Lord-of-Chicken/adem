<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ParticipationTier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for ParticipationTier entity.
 *
 * @extends ServiceEntityRepository<ParticipationTier>
 */
class ParticipationTierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipationTier::class);
    }

    /**
     * Finds all active participation tiers ordered by sort order then id.
     *
     * Single query used by ParticipationCatalog to build its per-request cache.
     *
     * @return list<ParticipationTier> List of all active tiers
     */
    public function findAllActiveOrdered(): array
    {
        /** @var list<ParticipationTier> */
        return $this->createQueryBuilder('t')
            ->andWhere('t.active = true')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds active participation tiers by group ordered by sort order.
     *
     * @param string $group The tier group (e.g., 'standard', 'vip')
     * @return list<ParticipationTier> List of active tiers in the group
     */
    public function findActiveByGroupOrdered(string $group): array
    {
        /** @var list<ParticipationTier> */
        return $this->createQueryBuilder('t')
            ->andWhere('t.tierGroup = :g')
            ->andWhere('t.active = true')
            ->setParameter('g', $group)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
