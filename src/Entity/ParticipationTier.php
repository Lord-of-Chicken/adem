<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ParticipationTierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a participation tier with pricing and quantity rules.
 */
#[ORM\Entity(repositoryClass: ParticipationTierRepository::class)]
#[ORM\Table(name: 'participation_tier')]
class ParticipationTier
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $detail;

    #[ORM\Column(name: 'price_label', length: 64)]
    private string $priceLabel;

    #[ORM\Column(name: 'price_unit', length: 8)]
    private string $priceUnit = '€';

    #[ORM\Column(name: 'price_suffix', length: 64, nullable: true)]
    private ?string $priceSuffix = null;

    #[ORM\Column(name: 'unit_price_eur', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPriceEur;

    #[ORM\Column(name: 'priced_per_unit')]
    private bool $pricedPerUnit = false;

    #[ORM\Column(name: 'min_qty')]
    private int $minQty = 1;

    #[ORM\Column(name: 'max_qty')]
    private int $maxQty = 1;

    #[ORM\Column(name: 'tier_group', length: 16)]
    private string $tierGroup = 'standard';

    #[ORM\Column(name: 'donor_field')]
    private bool $donorField = false;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $active = true;

    /**
     * Gets the tier ID.
     *
     * @return string The tier ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Sets the tier ID.
     *
     * @param string $id The tier ID
     * @return static The entity for method chaining
     */
    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets the tier title.
     *
     * @return string The title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets the tier title.
     *
     * @param string $title The title
     * @return static The entity for method chaining
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets the tier detail/description.
     *
     * @return string The detail
     */
    public function getDetail(): string
    {
        return $this->detail;
    }

    /**
     * Sets the tier detail/description.
     *
     * @param string $detail The detail
     * @return static The entity for method chaining
     */
    public function setDetail(string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    /**
     * Gets the price label.
     *
     * @return string The price label
     */
    public function getPriceLabel(): string
    {
        return $this->priceLabel;
    }

    /**
     * Sets the price label.
     *
     * @param string $priceLabel The price label
     * @return static The entity for method chaining
     */
    public function setPriceLabel(string $priceLabel): static
    {
        $this->priceLabel = $priceLabel;

        return $this;
    }

    /**
     * Gets the price unit (currency symbol).
     *
     * @return string The price unit
     */
    public function getPriceUnit(): string
    {
        return $this->priceUnit;
    }

    /**
     * Sets the price unit (currency symbol).
     *
     * @param string $priceUnit The price unit
     * @return static The entity for method chaining
     */
    public function setPriceUnit(string $priceUnit): static
    {
        $this->priceUnit = $priceUnit;

        return $this;
    }

    /**
     * Gets the price suffix (e.g., '/person').
     *
     * @return string|null The price suffix
     */
    public function getPriceSuffix(): ?string
    {
        return $this->priceSuffix;
    }

    /**
     * Sets the price suffix.
     *
     * @param string|null $priceSuffix The price suffix
     * @return static The entity for method chaining
     */
    public function setPriceSuffix(?string $priceSuffix): static
    {
        $this->priceSuffix = $priceSuffix;

        return $this;
    }

    /**
     * Gets the unit price in EUR.
     *
     * @return string The unit price
     */
    public function getUnitPriceEur(): string
    {
        return $this->unitPriceEur;
    }

    /**
     * Sets the unit price in EUR.
     *
     * @param string $unitPriceEur The unit price
     * @return static The entity for method chaining
     */
    public function setUnitPriceEur(string $unitPriceEur): static
    {
        $this->unitPriceEur = $unitPriceEur;

        return $this;
    }

    /**
     * Checks if priced per unit.
     *
     * @return bool True if priced per unit
     */
    public function isPricedPerUnit(): bool
    {
        return $this->pricedPerUnit;
    }

    /**
     * Sets if priced per unit.
     *
     * @param bool $pricedPerUnit Whether priced per unit
     * @return static The entity for method chaining
     */
    public function setPricedPerUnit(bool $pricedPerUnit): static
    {
        $this->pricedPerUnit = $pricedPerUnit;

        return $this;
    }

    /**
     * Gets the minimum quantity.
     *
     * @return int The minimum quantity
     */
    public function getMinQty(): int
    {
        return $this->minQty;
    }

    /**
     * Sets the minimum quantity.
     *
     * @param int $minQty The minimum quantity
     * @return static The entity for method chaining
     */
    public function setMinQty(int $minQty): static
    {
        $this->minQty = $minQty;

        return $this;
    }

    /**
     * Gets the maximum quantity.
     *
     * @return int The maximum quantity
     */
    public function getMaxQty(): int
    {
        return $this->maxQty;
    }

    /**
     * Sets the maximum quantity.
     *
     * @param int $maxQty The maximum quantity
     * @return static The entity for method chaining
     */
    public function setMaxQty(int $maxQty): static
    {
        $this->maxQty = $maxQty;

        return $this;
    }

    /**
     * Gets the tier group (standard, vip, etc.).
     *
     * @return string The tier group
     */
    public function getTierGroup(): string
    {
        return $this->tierGroup;
    }

    /**
     * Sets the tier group.
     *
     * @param string $tierGroup The tier group
     * @return static The entity for method chaining
     */
    public function setTierGroup(string $tierGroup): static
    {
        $this->tierGroup = $tierGroup;

        return $this;
    }

    /**
     * Checks if donor name field is enabled.
     *
     * @return bool True if donor field is enabled
     */
    public function isDonorField(): bool
    {
        return $this->donorField;
    }

    /**
     * Sets if donor name field is enabled.
     *
     * @param bool $donorField Whether donor field is enabled
     * @return static The entity for method chaining
     */
    public function setDonorField(bool $donorField): static
    {
        $this->donorField = $donorField;

        return $this;
    }

    /**
     * Gets the sort order.
     *
     * @return int The sort order
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Sets the sort order.
     *
     * @param int $sortOrder The sort order
     * @return static The entity for method chaining
     */
    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * Checks if the tier is active.
     *
     * @return bool True if active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Sets the active status.
     *
     * @param bool $active Whether the tier is active
     * @return static The entity for method chaining
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Converts the tier to a catalog array format.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     detail: string,
     *     price: string,
     *     price_unit: string,
     *     price_suffix: string|null,
     *     unit_price_eur: float,
     *     priced_per_unit: bool,
     *     min_qty: int,
     *     max_qty: int,
     *     group: string,
     *     donor_field: bool
     * }
     */
    public function toCatalogArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'detail' => $this->detail,
            'price' => $this->priceLabel,
            'price_unit' => $this->priceUnit,
            'price_suffix' => $this->priceSuffix,
            'unit_price_eur' => (float) $this->unitPriceEur,
            'priced_per_unit' => $this->pricedPerUnit,
            'min_qty' => $this->minQty,
            'max_qty' => $this->maxQty,
            'group' => $this->tierGroup,
            'donor_field' => $this->donorField,
        ];
    }
}
