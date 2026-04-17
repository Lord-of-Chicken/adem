<?php

namespace App\Cart;

use App\Participation\ParticipationCatalog;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    private const SESSION_KEY = 'participation_cart_v1';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        return $request->getSession();
    }

    /**
     * @return list<array{line_id: string, tier_id: string, quantity: int, donor_name: string|null}>
     */
    public function getLines(): array
    {
        $session = $this->session();
        if (null === $session) {
            return [];
        }

        /** @var list<array{line_id: string, tier_id: string, quantity: int, donor_name: string|null}>|null $lines */
        $lines = $session->get(self::SESSION_KEY);

        return \is_array($lines) ? $lines : [];
    }

    public function countLines(): int
    {
        return \count($this->getLines());
    }

    public function clear(): void
    {
        $session = $this->session();
        if (null !== $session) {
            $session->set(self::SESSION_KEY, []);
        }
    }

    public function addLine(string $tierId, int $quantity, ?string $donorName, ParticipationCatalog $catalog): void
    {
        $tier = $catalog->require($tierId);
        if ($tier['priced_per_unit']) {
            $quantity = max($tier['min_qty'], min($tier['max_qty'], $quantity));
        } else {
            $quantity = 1;
        }

        $donorName = $this->normalizeDonor($donorName, $tier['donor_field']);

        $session = $this->session();
        if (null === $session) {
            throw new \LogicException('Session requise pour modifier le panier.');
        }

        $lines = $this->getLines();
        // Vérifier si une ligne avec ce tier_id existe déjà
        $existingLine = null;
        foreach ($lines as $existingLine) {
            if ($existingLine['tier_id'] === $tierId && $existingLine['donor_name'] === $donorName) {
                $existingLine = $existingLine;
                break;
            }
        }
        
        if ($existingLine) {
            // Mettre à jour la quantité de la ligne existante
            $existingLine['quantity'] += $quantity;
            $session->set(self::SESSION_KEY, $lines);
            return;
        }
        
        $lines[] = [
            'line_id' => $tierId . '_' . uniqid(),
            'tier_id' => $tierId,
            'quantity' => $quantity,
            'donor_name' => $donorName,
        ];
        $session->set(self::SESSION_KEY, $lines);
    }

    public function removeLine(string $lineId): void
    {
        $session = $this->session();
        if (null === $session) {
            return;
        }

        $filtered = array_values(array_filter(
            $this->getLines(),
            static fn (array $line): bool => $line['line_id'] !== $lineId,
        ));
        $session->set(self::SESSION_KEY, $filtered);
    }

    public function updateQuantity(string $lineId, int $quantity, ParticipationCatalog $catalog): void
    {
        $session = $this->session();
        if (null === $session) {
            return;
        }

        $lines = $this->getLines();
        foreach ($lines as $i => $line) {
            if ($line['line_id'] !== $lineId) {
                continue;
            }
            $tier = $catalog->require($line['tier_id']);
            if (!$tier['priced_per_unit']) {
                return;
            }
            $lines[$i]['quantity'] = max($tier['min_qty'], min($tier['max_qty'], $quantity));
            $session->set(self::SESSION_KEY, $lines);

            return;
        }
    }

    public function totalCents(ParticipationCatalog $catalog): int
    {
        $sum = 0;
        foreach ($this->getLines() as $line) {
            $tier = $catalog->require($line['tier_id']);
            $sum += $catalog->lineTotalCents($tier, $line['quantity']);
        }

        return $sum;
    }

    private function normalizeDonor(?string $donorName, bool $allowed): ?string
    {
        if (!$allowed) {
            return null;
        }
        $trimmed = null === $donorName ? '' : trim($donorName);

        return '' === $trimmed ? null : $trimmed;
    }
}
