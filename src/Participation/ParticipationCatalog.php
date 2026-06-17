<?php

declare(strict_types=1);

namespace App\Participation;

use App\Repository\ParticipationTierRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Catalogue des formules.
 *
 * Source de vérité : la table `participation_tier` (entité {@see \App\Entity\ParticipationTier}),
 * éditable par la famille via l'admin EasyAdmin (prix, ordre, min/max, groupe, flags).
 *
 * Le fichier config/tiers.yaml n'est PLUS lu à l'exécution : il sert uniquement de source
 * de seed (cf. App\Command\SeedParticipationTiersCommand) pour peupler la table avec les
 * valeurs historiques. Les titres / descriptions restent traduisibles via les clés *_key
 * stockées dans les colonnes title/detail/price/price_suffix.
 *
 * L'accès est mis en cache en mémoire pour la durée de la requête : la table n'est lue
 * qu'une seule fois (chargement paresseux au premier appel de all()/get()/require()).
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
    /**
     * Per-request in-memory cache of the tiers, indexed by id.
     * Null until the first DB read.
     *
     * @var array<string, Tier>|null
     */
    private ?array $tiers = null;

    public function __construct(
        private readonly ParticipationTierRepository $repository,
    ) {
    }

    /**
     * Loads the active tiers from the database once and caches them in memory.
     *
     * Ordered by sort order then id, so the consumer-facing ordering is
     * deterministic and driven by the admin-editable sort_order column.
     *
     * @return array<string, Tier> All tiers indexed by ID
     */
    private function load(): array
    {
        if ($this->tiers !== null) {
            return $this->tiers;
        }

        $tiers = [];
        foreach ($this->repository->findAllActiveOrdered() as $entity) {
            $tiers[$entity->getId()] = $entity->toCatalogArray();
        }

        return $this->tiers = $tiers;
    }

    /**
     * Gets all tiers in the catalog.
     *
     * @return array<string, Tier> All tiers indexed by ID
     */
    public function all(): array
    {
        return $this->load();
    }

    /**
     * Gets a tier by ID.
     *
     * @param string $id The tier ID
     * @return Tier|null The tier or null if not found
     */
    public function get(string $id): ?array
    {
        return $this->load()[$id] ?? null;
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
     * The DB stores the translation KEYS in the title/detail/price/price_suffix
     * columns (e.g. "home.tier_begonia_title"). Only values that look like a
     * translation key (the "home." catalog domain prefix) are translated; literal
     * strings entered in the admin are left untouched. This preserves the previous
     * YAML behaviour where only *_key entries were resolved.
     *
     * @param Tier $tier The tier data (carries translation keys from DB)
     * @param TranslatorInterface $translator The translator service
     * @return Tier The tier with translated title/detail/price/price_suffix
     */
    public function translateTier(array $tier, TranslatorInterface $translator): array
    {
        $tier['title'] = $this->translateKey($tier['title'], $translator);
        $tier['detail'] = $this->translateKey($tier['detail'], $translator);
        $tier['price'] = $this->translateKey($tier['price'], $translator);
        if ($tier['price_suffix'] !== null) {
            $tier['price_suffix'] = $this->translateKey($tier['price_suffix'], $translator);
        }

        return $tier;
    }

    /**
     * Translates a value if it looks like a translation key, otherwise returns it as-is.
     *
     * @param string $value The stored value (translation key or literal)
     * @param TranslatorInterface $translator The translator service
     * @return string The translated value or the literal value
     */
    private function translateKey(string $value, TranslatorInterface $translator): string
    {
        if (str_starts_with($value, 'home.')) {
            return $translator->trans($value);
        }

        return $value;
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

        foreach ($this->load() as $tier) {
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
