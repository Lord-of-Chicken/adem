<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterConfirmationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Double opt-in token for newsletter subscription (GDPR-compliant consent proof).
 *
 * A confirmation is created when a user requests the newsletter. The newsletter
 * flag is only set on the User once the matching token is confirmed via email.
 */
#[ORM\Entity(repositoryClass: NewsletterConfirmationRepository::class)]
#[ORM\Table(name: 'newsletter_confirmation')]
#[ORM\Index(fields: ['email'], name: 'idx_newsletter_confirmation_email')]
class NewsletterConfirmation
{
    /** @var int Token validity duration in days */
    public const TOKEN_TTL_DAYS = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * @param string $email The subscriber email
     * @param string $token A unique, unguessable confirmation token
     */
    public function __construct(string $email, string $token)
    {
        $this->email = $email;
        $this->token = $token;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(sprintf('+%d days', self::TOKEN_TTL_DAYS));
    }

    /**
     * Gets the confirmation ID.
     *
     * @return int|null The ID or null if not persisted
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the subscriber email.
     *
     * @return string The email
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Gets the confirmation token.
     *
     * @return string The token
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Gets the creation date.
     *
     * @return \DateTimeImmutable The creation date
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Gets the token expiration date.
     *
     * @return \DateTimeImmutable The expiration date
     */
    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Gets the confirmation date.
     *
     * @return \DateTimeImmutable|null The confirmation date or null if not yet confirmed
     */
    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /**
     * Marks the confirmation as confirmed (idempotent).
     *
     * @return void
     */
    public function confirm(): void
    {
        $this->confirmedAt ??= new \DateTimeImmutable();
    }

    /**
     * Checks whether the token has already been confirmed.
     *
     * @return bool True if confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }

    /**
     * Checks whether the token has expired.
     *
     * @return bool True if expired
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
