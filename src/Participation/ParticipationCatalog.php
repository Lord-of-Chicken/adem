<?php

declare(strict_types=1);

namespace App\Participation;

use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Catalogue des formules (définitions dans config/tiers.yaml).
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
    private const TIERS_FILE = __DIR__ . '/../../config/tiers.yaml';

    /** @var array<string, Tier> */
    private array $tiers;

    /**
     * Loads tiers from the YAML configuration file.
     */
    public function __construct()
    {
        $this->tiers = [];
        /** @var array{tiers: array<int, Tier>} */
        $tiersData = Yaml::parseFile(self::TIERS_FILE);
        foreach ($tiersData['tiers'] as $def) {
            $this->tiers[$def['id']] = $def;
        }
    }

    /**
     * Gets all tiers in the catalog.
     *
     * @return array<string, Tier> All tiers indexed by ID
     */
    public function all(): array
    {
        return $this->tiers;
    }

    /**
     * Gets a tier by ID.
     *
     * @param string $id The tier ID
     * @return Tier|null The tier or null if not found
     */
    public function get(string $id): ?array
    {
        return $this->tiers[$id] ?? null;
    }

    /**
     * Gets a tier by ID or throws exception if not found.
     *
     * @param string $id The tier ID
     * @return Tier The tier
     * @throws \InvalidArgumentException If tier is not found
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
     * Resolves a tier's translatable keys (title/detail/price/price_suffix)
     * into translated strings, in place.
     *
     * Only keys actually present on the tier are translated, preserving the
     * exact behaviour previously duplicated across the controllers.
     *
     * @param Tier $tier The tier data (may carry *_key entries from YAML)
     * @param TranslatorInterface $translator The translator service
     * @return Tier The tier with translated title/detail/price/price_suffix
     */
    public function translateTier(array $tier, TranslatorInterface $translator): array
    {
        if (isset($tier['title_key'])) {
            $tier['title'] = $translator->trans($tier['title_key']);
        }
        if (isset($tier['detail_key'])) {
            $tier['detail'] = $translator->trans($tier['detail_key']);
        }
        if (isset($tier['price_key'])) {
            $tier['price'] = $translator->trans($tier['price_key']);
        }
        if (isset($tier['price_suffix_key'])) {
            $tier['price_suffix'] = $translator->trans($tier['price_suffix_key']);
        }

        return $tier;
    }

    /**
     * Returns the catalog tiers split by group with their translatable keys resolved.
     *
     * @param TranslatorInterface $translator The translator service
     * @return array{standard: list<Tier>, vip: list<Tier>} Translated tiers per group
     */
    public function getTranslatedTiers(TranslatorInterface $translator): array
    {
        $standard = [];
        $vip = [];

        foreach ($this->tiers as $tier) {
            $translated = $this->translateTier($tier, $translator);
            if ($tier['group'] === 'vip') {
                $vip[] = $translated;
            } else {
                $standard[] = $translated;
            }
        }

        return ['standard' => $standard, 'vip' => $vip];
    }

    /**
     * Converts a Euro amount to integer cents.
     *
     * Single source of truth for the EUR→cents conversion used across the cart,
     * checkout and catalog. Keeps rounding behaviour consistent everywhere.
     *
     * @param string|float $eur The amount in Euros
     * @return int The amount in cents
     */
    public static function eurToCents(string|float $eur): int
    {
        return (int) round((float) $eur * 100);
    }

    /**
     * Calculates the total price in cents for a tier line.
     *
     * @param Tier $tier The tier data
     * @param int $quantity The quantity
     * @return int Total price in cents
     */
    public function lineTotalCents(array $tier, int $quantity): int
    {
        $quantity = max(1, $quantity);
        if ($tier['priced_per_unit']) {
            return self::eurToCents($tier['unit_price_eur']) * $quantity;
        }

        return self::eurToCents($tier['unit_price_eur']);
    }

    /**
     * Formats cents to a Euro string.
     *
     * @param int $cents The amount in cents
     * @return string Formatted Euro string
     */
    public function formatEurosFromCents(int $cents): string
    {
        $euros = $cents / 100;

        return number_format($euros, 2, ',', ' ');
    }
}
