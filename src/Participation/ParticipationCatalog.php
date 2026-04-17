<?php

namespace App\Participation;

use App\Entity\ParticipationTier;
use App\Repository\ParticipationTierRepository;

/**
 * Catalogue des formules (données Doctrine / PostgreSQL).
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
    public function __construct(
        private readonly ParticipationTierRepository $tierRepository,
    ) {
    }

    /** @return Tier|null */
    public function get(string $id): ?array
    {
        $entity = $this->tierRepository->find($id);
        if (!$entity instanceof ParticipationTier || !$entity->isActive()) {
            return null;
        }

        return $entity->toCatalogArray();
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

    /** @return list<Tier> */
    public function standardForHome(): array
    {
        return array_map(
            static fn (ParticipationTier $t) => $t->toCatalogArray(),
            $this->tierRepository->findActiveByGroupOrdered('standard'),
        );
    }

    /** @return list<Tier> */
    public function vipForHome(): array
    {
        return array_map(
            static fn (ParticipationTier $t) => $t->toCatalogArray(),
            $this->tierRepository->findActiveByGroupOrdered('vip'),
        );
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
