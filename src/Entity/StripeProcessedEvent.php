<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StripeProcessedEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records Stripe webhook events that have already been processed,
 * to guarantee webhook idempotency.
 */
#[ORM\Entity(repositoryClass: StripeProcessedEventRepository::class)]
#[ORM\Table(name: 'stripe_processed_event')]
#[ORM\UniqueConstraint(name: 'uniq_stripe_event_id', columns: ['stripe_event_id'])]
class StripeProcessedEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $stripeEventId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $processedAt;

    public function __construct(string $stripeEventId)
    {
        $this->stripeEventId = $stripeEventId;
        $this->processedAt = new \DateTimeImmutable();
    }

    /**
     * Gets the entity ID.
     *
     * @return int|null The ID or null if not persisted
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the Stripe event ID.
     *
     * @return string The Stripe event ID
     */
    public function getStripeEventId(): string
    {
        return $this->stripeEventId;
    }

    /**
     * Gets the date the event was processed.
     *
     * @return \DateTimeImmutable The processing date
     */
    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
