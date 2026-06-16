<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterConfirmation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for NewsletterConfirmation entity.
 *
 * @extends ServiceEntityRepository<NewsletterConfirmation>
 */
class NewsletterConfirmationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterConfirmation::class);
    }

    /**
     * Finds a confirmation by its token.
     *
     * @param string $token The confirmation token
     * @return NewsletterConfirmation|null The confirmation or null if not found
     */
    public function findOneByToken(string $token): ?NewsletterConfirmation
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Persists a confirmation.
     *
     * @param NewsletterConfirmation $confirmation The confirmation to persist
     * @param bool $flush Whether to flush immediately
     * @return void
     */
    public function save(NewsletterConfirmation $confirmation, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($confirmation);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Deletes expired, never-confirmed tokens (GDPR data minimisation).
     *
     * Confirmed tokens are kept as consent proof; only stale pending tokens
     * past their expiry are removed.
     *
     * @param \DateTimeImmutable|null $now Reference time (defaults to current time)
     * @return int Number of deleted rows
     */
    public function deleteExpiredUnconfirmed(?\DateTimeImmutable $now = null): int
    {
        return (int) $this->createQueryBuilder('nc')
            ->delete()
            ->where('nc.confirmedAt IS NULL')
            ->andWhere('nc.expiresAt < :now')
            ->setParameter('now', $now ?? new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
