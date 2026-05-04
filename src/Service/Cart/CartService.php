<?php

namespace App\Service\Cart;

use App\Service\Participation\ParticipationCatalog;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    private const SESSION_KEY = 'participation_cart_v1';

    public function __construct(private readonly RequestStack $requestStack) {}

    private function session(): ?SessionInterface
    {
        return $this->requestStack->getCurrentRequest()?->getSession();
    }

    public function getLines(): array
    {
        return $this->session()?->get(self::SESSION_KEY) ?? [];
    }

    public function totalCents(ParticipationCatalog $catalog): int
    {
        $total = 0;
        foreach ($this->getLines() as $line) {
            if (isset($line['custom_price_cents']) && $line['custom_price_cents'] !== null) {
                $priceCents = (int) $line['custom_price_cents'];
            } else {
                $tier = $catalog->require($line['tier_id']);
                $priceCents = (int) (round((float)$tier['unit_price_eur'] * 100));
            }
            $total += $priceCents * $line['quantity'];
        }
        return $total;
    }

    public function addLine(
        string $tierId, 
        int $quantity, 
        ?string $donorName, 
        ParticipationCatalog $catalog, 
        ?int $customPriceCents = null
    ): void {
        $session = $this->session();
        if (!$session) return;

        $tier = $catalog->require($tierId);
        $lines = $this->getLines();
        $normalizedDonor = $this->normalizeDonor($donorName, $tier['donor_field'] ?? false);

        foreach ($lines as $i => $line) {
            if ($line['tier_id'] === $tierId && 
                $line['donor_name'] === $normalizedDonor && 
                ($line['custom_price_cents'] ?? null) === $customPriceCents) {
                
                $newQty = $line['quantity'] + $quantity;
                $lines[$i]['quantity'] = max($tier['min_qty'], min($tier['max_qty'], $newQty));
                
                $session->set(self::SESSION_KEY, $lines);
                return;
            }
        }

        $lines[] = [
            'line_id'    => bin2hex(random_bytes(8)),
            'tier_id'    => $tierId,
            'quantity'   => max($tier['min_qty'], min($tier['max_qty'], $quantity)),
            'donor_name' => $normalizedDonor,
            'custom_price_cents' => $customPriceCents,
        ];

        $session->set(self::SESSION_KEY, $lines);
    }

    public function updateQuantity(string $lineId, int $quantity, ParticipationCatalog $catalog): void
    {
        $session = $this->session();
        if (!$session) return;

        $lines = $this->getLines();
        foreach ($lines as $i => $line) {
            if ($line['line_id'] === $lineId) {
                $tier = $catalog->require($line['tier_id']);
                $lines[$i]['quantity'] = max($tier['min_qty'], min($tier['max_qty'], $quantity));
                $session->set(self::SESSION_KEY, $lines);
                return;
            }
        }
    }

    public function removeLine(string $lineId): void
    {
        $session = $this->session();
        if (!$session) return;

        $lines = array_filter($this->getLines(), fn($l) => $l['line_id'] !== $lineId);
        $session->set(self::SESSION_KEY, array_values($lines));
    }

    public function clear(): void
    {
        $this->session()?->remove(self::SESSION_KEY);
    }

    private function normalizeDonor(?string $donorName, bool $allowed): ?string
    {
        if (!$allowed) return null;
        $trimmed = trim($donorName ?? '');
        return $trimmed === '' ? null : $trimmed;
    }

    public function countLines(): int 
    { 
        return count($this->getLines()); 
    }
}