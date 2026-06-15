<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Represents a user in the system.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity('email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $newsletter = false;

    /** @var list<string> The user roles */
    #[ORM\Column]
    private array $roles = [];

    /** @var string The hashed password */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    /**
     * Orders are intentionally NOT cascade-removed: they carry a 7-year fiscal
     * retention obligation. On account deletion the user is anonymised instead.
     *
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    /**
     * Gets the user ID.
     *
     * @return int|null The ID or null if not persisted
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the user email.
     *
     * @return string|null The email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the user email.
     *
     * @param string $email The email
     * @return static The entity for method chaining
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gets the user's first name.
     *
     * @return string|null The first name
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * Sets the user's first name.
     *
     * @param string|null $firstName The first name
     * @return static The entity for method chaining
     */
    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * Gets the user's last name.
     *
     * @return string|null The last name
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * Sets the user's last name.
     *
     * @param string|null $lastName The last name
     * @return static The entity for method chaining
     */
    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Gets the user's address.
     *
     * @return string|null The address
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * Sets the user's address.
     *
     * @param string|null $address The address
     * @return static The entity for method chaining
     */
    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Checks if the user is subscribed to the newsletter.
     *
     * @return bool True if subscribed
     */
    public function isNewsletter(): bool
    {
        return $this->newsletter;
    }

    /**
     * Sets the newsletter subscription status.
     *
     * @param bool $newsletter Whether subscribed to newsletter
     * @return static The entity for method chaining
     */
    public function setNewsletter(bool $newsletter): static
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        /** @var non-empty-string */
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * Sets the user roles.
     *
     * @param list<string> $roles The roles
     * @return static The entity for method chaining
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Sets the hashed password.
     *
     * @param string $password The hashed password
     * @return static The entity for method chaining
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Gets the password reset token.
     *
     * @return string|null The reset token
     */
    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    /**
     * Sets the password reset token.
     *
     * @param string|null $resetToken The reset token
     * @return static The entity for method chaining
     */
    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    /**
     * Gets the reset token expiration date.
     *
     * @return \DateTimeImmutable|null The expiration date
     */
    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    /**
     * Sets the reset token expiration date.
     *
     * @param \DateTimeImmutable|null $resetTokenExpiresAt The expiration date
     * @return static The entity for method chaining
     */
    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;

        return $this;
    }

    /**
     * Gets the anonymisation date (GDPR right to erasure).
     *
     * @return \DateTimeImmutable|null The anonymisation date or null if the account is active
     */
    public function getAnonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    /**
     * Sets the anonymisation date.
     *
     * @param \DateTimeImmutable|null $anonymizedAt The anonymisation date or null
     * @return static The entity for method chaining
     */
    public function setAnonymizedAt(?\DateTimeImmutable $anonymizedAt): static
    {
        $this->anonymizedAt = $anonymizedAt;

        return $this;
    }

    /**
     * Checks whether the account has been anonymised (GDPR erasure).
     *
     * @return bool True if anonymised
     */
    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }

    /**
     * Gets the user's orders.
     *
     * @return Collection<int, Order> The orders
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    /**
     * Adds an order to the user.
     *
     * @param Order $order The order to add
     * @return static The entity for method chaining
     */
    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }

        return $this;
    }

    /**
     * Removes an order from the user.
     *
     * @param Order $order The order to remove
     * @return static The entity for method chaining
     */
    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }
}
