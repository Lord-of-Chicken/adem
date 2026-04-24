<?php

namespace App\Participation;

use App\Controller\HomeController;

/**
 * Catalogue des formules (définitions dans HomeController::TIERS).
 *
 * @phpstan-type Tier array{
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
final class ParticipationCatalog
{
    /** @var array<string, Tier> */
    private array $tiers;

    public function __construct()
    {
        $this->tiers = [];
        foreach (HomeController::TIERS as $def) {
            $this->tiers[$def['id']] = $def;
        }
    }

    /** @return array<string, Tier> */
    public function all(): array
    {
        return $this->tiers;
    }

    /** @return Tier|null */
    public function get(string $id): ?array
    {
        return $this->tiers[$id] ?? null;
    }

    /**
     * @return Tier
     */
    public function require(string $id): array
    {
        $tier = $this->get($id);
        if (null === $tier) {
            throw new \InvalidArgumentException(sprintf('Formule inconnue : %s', $id));
        }

        return $tier;
    }

    /**
     * @param Tier $tier
     */
    public function lineTotalCents(array $tier, int $quantity): int
    {
        $quantity = max(1, $quantity);
        if ($tier['priced_per_unit']) {
            return (int) round($tier['unit_price_eur'] * 100) * $quantity;
        }

        return (int) round($tier['unit_price_eur'] * 100);
    }

    public function formatEurosFromCents(int $cents): string
    {
        $euros = $cents / 100;

        return number_format($euros, 2, ',', ' ');
    }
}
