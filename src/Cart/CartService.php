<?php

declare(strict_types=1);

namespace App\Cart;

use App\Participation\ParticipationCatalog;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Service for managing shopping cart in session.
 */
final class CartService
{
    /** @var string Session key for storing cart data */
    private const SESSION_KEY = 'participation_cart_v1';

    /** @var int Minimum allowed custom/free amount in cents (1 €) */
    public const MIN_CUSTOM_PRICE_CENTS = 100;

    /** @var int Maximum allowed custom/free amount in cents (100 000 €) */
    public const MAX_CUSTOM_PRICE_CENTS = 10_000_000;

    /** @var int Maximum allowed donor name length */
    public const MAX_DONOR_NAME_LENGTH = 255;

    public function __construct(private readonly RequestStack $requestStack) {}

    /**
     * Gets the current session.
     *
     * @return SessionInterface|null The session or null if not available
     */
    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request !== null && $request->hasSession() ? $request->getSession() : null;
    }

    /**
     * Gets all cart lines from session.
     *
     * @return array<int, array{line_id: string, tier_id: string, quantity: int, donor_name: string|null, custom_price_cents: int|null}> The cart lines
     */
    public function getLines(): array
    {
        $lines = $this->session()?->get(self::SESSION_KEY) ?? [];
        if (!is_array($lines)) {
            throw new \UnexpectedValueException('Invalid cart session.');
        }
        $validated = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !isset($line['line_id'], $line['tier_id'], $line['quantity'])
                || !is_string($line['line_id']) || !is_string($line['tier_id']) || !is_int($line['quantity'])
                || !array_key_exists('donor_name', $line) || (!is_string($line['donor_name']) && $line['donor_name'] !== null)
                || !array_key_exists('custom_price_cents', $line) || (!is_int($line['custom_price_cents']) && $line['custom_price_cents'] !== null)) {
                throw new \UnexpectedValueException('Invalid cart line.');
            }
            $validated[] = [
                'line_id' => $line['line_id'], 'tier_id' => $line['tier_id'], 'quantity' => $line['quantity'],
                'donor_name' => $line['donor_name'], 'custom_price_cents' => $line['custom_price_cents'],
            ];
        }
        return $validated;
    }

    /**
     * Calculates total cart value in cents.
     *
     * @param ParticipationCatalog $catalog The participation catalog
     * @return int Total value in cents
     */
    public function totalCents(ParticipationCatalog $catalog): int
    {
        $total = 0;
        $firstTotals = [];
        foreach ($this->getLines() as $line) {
            // Preserve the first matching line when an existing session contains duplicate IDs.
            $lineId = $line['line_id'];
            if (!array_key_exists($lineId, $firstTotals)) {
                $firstTotals[$lineId] = $this->calculateLineTotal($line, $catalog);
            }
            $total += $firstTotals[$lineId];
        }
        return $total;
    }

    /**
     * Calculates the total in cents for a single cart line.
     *
     * @param string $lineId The line ID
     * @param ParticipationCatalog $catalog The participation catalog
     * @return int Total in cents for the line, or 0 if the line is not found
     */
    public function getLineTotal(string $lineId, ParticipationCatalog $catalog): int
    {
        foreach ($this->getLines() as $line) {
            if ($line['line_id'] === $lineId) {
                return $this->calculateLineTotal($line, $catalog);
            }
        }

        return 0;
    }

    /**
     * @param array{line_id: string, tier_id: string, quantity: int, donor_name: string|null, custom_price_cents: int|null} $line
     */
    private function calculateLineTotal(array $line, ParticipationCatalog $catalog): int
    {
        $tier = $catalog->require($line['tier_id']);
        $customPrice = $line['custom_price_cents'];
        return $customPrice !== null
            ? $customPrice * $line['quantity']
            : $catalog->lineTotalCents($tier, $line['quantity']);
    }

    /**
     * Adds or updates a line in the cart.
     *
     * @param string $tierId The participation tier ID
     * @param int $quantity The quantity to add
     * @param string|null $donorName The donor name (optional)
     * @param ParticipationCatalog $catalog The participation catalog
     * @param int|null $customPriceCents Custom price in cents for free donations
     * @return void
     * @throws \InvalidArgumentException If tier is not found
     */
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

        if (($tierId === 'free_donation') !== ($customPriceCents !== null)) {
            throw new \InvalidArgumentException('Custom price is required only for free donations.');
        }

        if ($customPriceCents !== null) {
            if ($customPriceCents < self::MIN_CUSTOM_PRICE_CENTS || $customPriceCents > self::MAX_CUSTOM_PRICE_CENTS) {
                throw new \InvalidArgumentException(sprintf(
                    'Custom price out of allowed range (%d–%d cents).',
                    self::MIN_CUSTOM_PRICE_CENTS,
                    self::MAX_CUSTOM_PRICE_CENTS
                ));
            }
        }

        $lines = $this->getLines();
        $normalizedDonor = $this->normalizeDonor($donorName, $tier['donor_field'] ?? false);

        if ($normalizedDonor !== null && mb_strlen($normalizedDonor) > self::MAX_DONOR_NAME_LENGTH) {
            throw new \InvalidArgumentException('Donor name exceeds maximum length.');
        }

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

    /**
     * Updates the quantity of a specific cart line.
     *
     * @param string $lineId The line ID to update
     * @param int $quantity The new quantity
     * @param ParticipationCatalog $catalog The participation catalog
     * @return void
     */
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

    /**
     * Removes a line from the cart.
     *
     * @param string $lineId The line ID to remove
     * @return void
     */
    public function removeLine(string $lineId): void
    {
        $session = $this->session();
        if (!$session) return;

        $lines = array_filter($this->getLines(), fn($l) => $l['line_id'] !== $lineId);
        $session->set(self::SESSION_KEY, array_values($lines));
    }

    /**
     * Clears all items from the cart.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->session()?->remove(self::SESSION_KEY);
    }

    /**
     * Normalizes donor name based on tier requirements.
     *
     * @param string|null $donorName The donor name to normalize
     * @param bool $allowed Whether donor name is allowed for this tier
     * @return string|null Normalized donor name or null
     */
    private function normalizeDonor(?string $donorName, bool $allowed): ?string
    {
        if (!$allowed) return null;
        $trimmed = trim($donorName ?? '');
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Counts the number of lines in the cart.
     *
     * @return int The number of cart lines
     */
    public function countLines(): int 
    { 
        return count($this->getLines()); 
    }
}