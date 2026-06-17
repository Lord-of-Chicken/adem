<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an order for participation tiers.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\Index(fields: ['stripeCheckoutSessionId'], name: 'idx_order_stripe_session')]
#[ORM\Index(fields: ['stripePaymentIntentId'], name: 'idx_order_stripe_payment_intent')]
#[ORM\Index(fields: ['status'], name: 'idx_order_status')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $stripeCheckoutSessionId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $totalCents = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $cartData = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = OrderStatus::Pending;
    }

    /**
     * Gets the order ID.
     *
     * @return int|null The ID or null if not persisted
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the Stripe checkout session ID.
     *
     * @return string|null The session ID
     */
    public function getStripeCheckoutSessionId(): ?string
    {
        return $this->stripeCheckoutSessionId;
    }

    /**
     * Sets the Stripe checkout session ID.
     *
     * @param string $stripeCheckoutSessionId The session ID
     * @return static The entity for method chaining
     */
    public function setStripeCheckoutSessionId(string $stripeCheckoutSessionId): static
    {
        $this->stripeCheckoutSessionId = $stripeCheckoutSessionId;

        return $this;
    }

    /**
     * Gets the Stripe payment intent ID.
     *
     * @return string|null The payment intent ID
     */
    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    /**
     * Sets the Stripe payment intent ID.
     *
     * @param string|null $stripePaymentIntentId The payment intent ID
     * @return static The entity for method chaining
     */
    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;

        return $this;
    }

    /**
     * Gets the order status.
     *
     * @return OrderStatus The status (pending, paid, failed)
     */
    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    /**
     * Sets the order status.
     *
     * @param OrderStatus $status The status
     * @return static The entity for method chaining
     */
    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Gets the total amount in cents.
     *
     * @return int|null The total in cents
     */
    public function getTotalCents(): ?int
    {
        return $this->totalCents;
    }

    /**
     * Sets the total amount in cents.
     *
     * @param int $totalCents The total in cents
     * @return static The entity for method chaining
     */
    public function setTotalCents(int $totalCents): static
    {
        $this->totalCents = $totalCents;

        return $this;
    }

    /**
     * Gets the cart data.
     *
     * @return array<string, mixed> The cart data
     */
    public function getCartData(): array
    {
        return $this->cartData;
    }

    /**
     * Sets the cart data.
     *
     * @param array<string, mixed> $cartData The cart data
     * @return static The entity for method chaining
     */
    public function setCartData(array $cartData): static
    {
        $this->cartData = $cartData;

        return $this;
    }

    /**
     * Gets the creation date.
     *
     * @return \DateTimeImmutable|null The creation date
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation date.
     *
     * @param \DateTimeImmutable $createdAt The creation date
     * @return static The entity for method chaining
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gets the payment date.
     *
     * @return \DateTimeImmutable|null The payment date or null if not paid
     */
    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    /**
     * Sets the payment date.
     *
     * @param \DateTimeImmutable|null $paidAt The payment date or null
     * @return static The entity for method chaining
     */
    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    /**
     * Gets the user who placed the order.
     *
     * @return User|null The user or null if guest order
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Sets the user who placed the order.
     *
     * @param User|null $user The user or null for guest order
     * @return static The entity for method chaining
     */
    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Marks the order as paid.
     *
     * @return static The entity for method chaining
     */
    public function markAsPaid(): static
    {
        $this->status = OrderStatus::Paid;
        $this->paidAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Marks the order as failed.
     *
     * @return static The entity for method chaining
     */
    public function markAsFailed(): static
    {
        $this->status = OrderStatus::Failed;

        return $this;
    }

    /**
     * Checks if the order is paid.
     *
     * @return bool True if the order is paid
     */
    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * Checks if the order has failed.
     *
     * @return bool True if the order has failed
     */
    public function isFailed(): bool
    {
        return $this->status === OrderStatus::Failed;
    }
}
