<?php

declare(strict_types=1);

namespace App\Tests\Cart;

use App\Cart\CartService;
use App\Participation\ParticipationCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Pure unit tests for the cart domain logic. No database, no kernel.
 *
 * The session is backed by MockArraySessionStorage so cart state lives entirely
 * in memory; the real ParticipationCatalog reads config/tiers.yaml (no I/O cost
 * beyond a single file read, no DB).
 */
final class CartServiceTest extends TestCase
{
    private function makeService(): CartService
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CartService($requestStack);
    }

    private function catalog(): ParticipationCatalog
    {
        return new ParticipationCatalog();
    }

    public function testTotalCentsWithFreeDonationUsesCustomPrice(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        // free_donation has unit_price_eur 0.00 — the total must come from custom_price_cents.
        $cart->addLine('free_donation', 1, 'Alice', $catalog, customPriceCents: 2500);

        self::assertSame(2500, $cart->totalCents($catalog));
    }

    public function testTotalCentsWithMixedTierAndFreeDonation(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        // Standard tier priced from the catalog: begonia_unit = 1.00 € = 100 cents, qty 3 => 300.
        $cart->addLine('begonia_unit', 3, null, $catalog);
        // Free donation with explicit amount: 5000 cents, qty 1 => 5000.
        $cart->addLine('free_donation', 1, 'Bob', $catalog, customPriceCents: 5000);

        self::assertSame(300 + 5000, $cart->totalCents($catalog));
    }

    public function testAddLineRejectsCustomPriceBelowMinimum(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        $this->expectException(\InvalidArgumentException::class);
        $cart->addLine('free_donation', 1, 'Carol', $catalog, customPriceCents: CartService::MIN_CUSTOM_PRICE_CENTS - 1);
    }

    public function testAddLineRejectsCustomPriceAboveMaximum(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        $this->expectException(\InvalidArgumentException::class);
        $cart->addLine('free_donation', 1, 'Dave', $catalog, customPriceCents: CartService::MAX_CUSTOM_PRICE_CENTS + 1);
    }

    public function testAddLineRejectsDonorNameExceedingMaxLength(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        // donor_name is only retained for tiers with donor_field=true (e.g. vip_20),
        // so the length guard can only trigger there.
        $tooLong = str_repeat('a', CartService::MAX_DONOR_NAME_LENGTH + 1);

        $this->expectException(\InvalidArgumentException::class);
        $cart->addLine('vip_20', 1, $tooLong, $catalog);
    }

    public function testAddLineAcceptsDonorNameAtMaxLength(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        $boundary = str_repeat('a', CartService::MAX_DONOR_NAME_LENGTH);
        $cart->addLine('vip_20', 1, $boundary, $catalog);

        $lines = $cart->getLines();
        self::assertCount(1, $lines);
        self::assertSame($boundary, $lines[0]['donor_name']);
    }

    public function testAddLineAcceptsCustomPriceAtBounds(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        $cart->addLine('free_donation', 1, null, $catalog, customPriceCents: CartService::MIN_CUSTOM_PRICE_CENTS);
        self::assertSame(CartService::MIN_CUSTOM_PRICE_CENTS, $cart->totalCents($catalog));
    }

    public function testAddLineRejectsUnknownTier(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();

        $this->expectException(\InvalidArgumentException::class);
        $cart->addLine('does_not_exist', 1, null, $catalog);
    }
}
