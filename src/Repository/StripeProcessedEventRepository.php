<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StripeProcessedEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for StripeProcessedEvent entity.
 *
 * @extends ServiceEntityRepository<StripeProcessedEvent>
 */
class StripeProcessedEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StripeProcessedEvent::class);
    }

    /**
     * Checks whether a Stripe event has already been processed.
     *
     * @param string $stripeEventId The Stripe event ID
     * @return bool True if the event was already processed
     */
    public function existsByStripeEventId(string $stripeEventId): bool
    {
        return $this->findOneBy(['stripeEventId' => $stripeEventId]) !== null;
    }

    /**
     * Persists a processed event marker and flushes immediately.
     *
     * The caller is responsible for handling a UniqueConstraintViolationException,
     * which signals that the same event was processed concurrently (idempotency).
     *
     * @param StripeProcessedEvent $event The processed event marker
     * @return void
     */
    public function save(StripeProcessedEvent $event): void
    {
        $em = $this->getEntityManager();
        $em->persist($event);
        $em->flush();
    }
}
