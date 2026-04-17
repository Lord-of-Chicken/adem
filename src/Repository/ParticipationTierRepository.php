<?php

namespace App\Repository;

use App\Entity\ParticipationTier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipationTier>
 */
class ParticipationTierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipationTier::class);
    }

    /** @return list<ParticipationTier> */
    public function findActiveByGroupOrdered(string $group): array
    {
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
