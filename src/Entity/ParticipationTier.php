<?php

namespace App\Entity;

use App\Repository\ParticipationTierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\Column(name: 'price_label', length: 16)]
    private string $priceLabel;

    #[ORM\Column(name: 'price_unit', length: 8)]
    private string $priceUnit = '€';

    #[ORM\Column(name: 'price_suffix', length: 32, nullable: true)]
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

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function setDetail(string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    public function getPriceLabel(): string
    {
        return $this->priceLabel;
    }

    public function setPriceLabel(string $priceLabel): static
    {
        $this->priceLabel = $priceLabel;

        return $this;
    }

    public function getPriceUnit(): string
    {
        return $this->priceUnit;
    }

    public function setPriceUnit(string $priceUnit): static
    {
        $this->priceUnit = $priceUnit;

        return $this;
    }

    public function getPriceSuffix(): ?string
    {
        return $this->priceSuffix;
    }

    public function setPriceSuffix(?string $priceSuffix): static
    {
        $this->priceSuffix = $priceSuffix;

        return $this;
    }

    public function getUnitPriceEur(): string
    {
        return $this->unitPriceEur;
    }

    public function setUnitPriceEur(string $unitPriceEur): static
    {
        $this->unitPriceEur = $unitPriceEur;

        return $this;
    }

    public function isPricedPerUnit(): bool
    {
        return $this->pricedPerUnit;
    }

    public function setPricedPerUnit(bool $pricedPerUnit): static
    {
        $this->pricedPerUnit = $pricedPerUnit;

        return $this;
    }

    public function getMinQty(): int
    {
        return $this->minQty;
    }

    public function setMinQty(int $minQty): static
    {
        $this->minQty = $minQty;

        return $this;
    }

    public function getMaxQty(): int
    {
        return $this->maxQty;
    }

    public function setMaxQty(int $maxQty): static
    {
        $this->maxQty = $maxQty;

        return $this;
    }

    public function getTierGroup(): string
    {
        return $this->tierGroup;
    }

    public function setTierGroup(string $tierGroup): static
    {
        $this->tierGroup = $tierGroup;

        return $this;
    }

    public function isDonorField(): bool
    {
        return $this->donorField;
    }

    public function setDonorField(bool $donorField): static
    {
        $this->donorField = $donorField;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
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
