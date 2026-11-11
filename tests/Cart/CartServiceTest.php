<?php

declare(strict_types=1);

namespace App\Tests\Cart;

use App\Cart\CartService;
use App\Entity\ParticipationTier;
use App\Participation\ParticipationCatalog;
use App\Repository\ParticipationTierRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Pure unit tests for the cart domain logic. No database, no kernel.
 *
 * The session is backed by MockArraySessionStorage so cart state lives entirely
 * in memory. The catalog is built from a mocked ParticipationTierRepository that
 * returns the same tier values the production seed loads, so the cart logic is
 * exercised against the DB-backed catalog without touching a real database.
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
        $repository = $this->createStub(ParticipationTierRepository::class);
        $repository->method('findAllActiveOrdered')->willReturn($this->seedTiers());

        return new ParticipationCatalog($repository);
    }

    /**
     * Builds the subset of tiers exercised by the cart tests, mirroring config/tiers.yaml.
     *
     * @return list<ParticipationTier>
     */
    private function seedTiers(): array
    {
        $begonia = (new ParticipationTier())
            ->setId('begonia_unit')
            ->setTitle('home.tier_begonia_title')
            ->setDetail('home.tier_begonia_detail')
            ->setPriceLabel('1')
            ->setPriceUnit('€')
            ->setPriceSuffix('home.tier_begonia_price_unit')
            ->setUnitPriceEur('1.00')
            ->setPricedPerUnit(true)
            ->setMinQty(1)
            ->setMaxQty(500)
            ->setTierGroup('standard')
            ->setDonorField(false)
            ->setSortOrder(0)
            ->setActive(true);

        $vip20 = (new ParticipationTier())
            ->setId('vip_20')
            ->setTitle('home.tier_vip20_title')
            ->setDetail('home.tier_vip20_detail')
            ->setPriceLabel('20')
            ->setPriceUnit('€')
            ->setPriceSuffix(null)
            ->setUnitPriceEur('20.00')
            ->setPricedPerUnit(false)
            ->setMinQty(1)
            ->setMaxQty(500)
            ->setTierGroup('vip')
            ->setDonorField(true)
            ->setSortOrder(2)
            ->setActive(true);

        $free = (new ParticipationTier())
            ->setId('free_donation')
            ->setTitle('home.tier_free_title')
            ->setDetail('home.tier_free_detail')
            ->setPriceLabel('home.tier_free_price')
            ->setPriceUnit('€')
            ->setPriceSuffix(null)
            ->setUnitPriceEur('0.00')
            ->setPricedPerUnit(false)
            ->setMinQty(1)
            ->setMaxQty(1)
            ->setTierGroup('standard')
            ->setDonorField(true)
            ->setSortOrder(4)
            ->setActive(true);

        return [$begonia, $vip20, $free];
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

    public function testFlatPriceNeverMultipliesByQuantity(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();
        $cart->addLine('vip_20', 5, 'Alice', $catalog);
        self::assertSame(2000, $cart->totalCents($catalog));
        self::assertSame(2000, $cart->getLineTotal($cart->getLines()[0]['line_id'], $catalog));
    }

    public function testMergeClampsQuantityAndNormalizesNames(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();
        $cart->addLine('begonia_unit', 499, 'Ignored', $catalog);
        $cart->addLine('begonia_unit', 10, null, $catalog);
        self::assertCount(1, $cart->getLines());
        self::assertSame(500, $cart->getLines()[0]['quantity']);
        self::assertNull($cart->getLines()[0]['donor_name']);
        $cart->addLine('vip_20', 1, ' Alice ', $catalog);
        $cart->addLine('vip_20', 1, 'Alice', $catalog);
        self::assertCount(2, $cart->getLines());
        self::assertSame('Alice', $cart->getLines()[1]['donor_name']);
    }

    public function testUpdateRemoveAndClearPreserveOtherLines(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();
        $cart->addLine('begonia_unit', 2, null, $catalog);
        $cart->addLine('vip_20', 1, 'Bob', $catalog);
        $id = $cart->getLines()[0]['line_id'];
        $cart->updateQuantity($id, -10, $catalog);
        self::assertSame(1, $cart->getLines()[0]['quantity']);
        $cart->updateQuantity($id, 9999, $catalog);
        self::assertSame(500, $cart->getLines()[0]['quantity']);
        $cart->updateQuantity('unknown', 3, $catalog);
        self::assertSame(500, $cart->getLines()[0]['quantity']);
        $cart->removeLine($id);
        self::assertSame('vip_20', $cart->getLines()[0]['tier_id']);
        self::assertSame(0, $cart->getLineTotal($id, $catalog));
        $cart->clear();
        self::assertSame(0, $cart->countLines());
        self::assertSame(0, $cart->totalCents($catalog));
    }

    public function testDifferentCustomAmountsRemainSeparate(): void
    {
        $cart = $this->makeService();
        $catalog = $this->catalog();
        $cart->addLine('free_donation', 1, null, $catalog, 100);
        $cart->addLine('free_donation', 1, null, $catalog, CartService::MAX_CUSTOM_PRICE_CENTS);
        self::assertSame(10_000_100, $cart->totalCents($catalog));
        self::assertSame(2, $cart->countLines());
    }

    public function testFixedTierRejectsCustomPriceOverride(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeService()->addLine('vip_20', 1, null, $this->catalog(), 100);
    }

    public function testFreeDonationRequiresExplicitAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeService()->addLine('free_donation', 1, null, $this->catalog());
    }

    public function testMissingRequestOrSessionIsSafe(): void
    {
        $stack = new RequestStack();
        $cart = new CartService($stack);
        self::assertSame([], $cart->getLines());
        $stack->push(new Request());
        $cart->addLine('begonia_unit', 1, null, $this->catalog());
        $cart->clear();
        self::assertSame(0, $cart->countLines());
    }

    public function testCorruptSessionIsRejected(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set('participation_cart_v1', [['quantity' => 'untrusted']]);
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);
        $this->expectException(\UnexpectedValueException::class);
        (new CartService($stack))->getLines();
    }

    /** @param list<array<string, mixed>> $lines */
    private function serviceWithLines(array $lines): CartService
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('participation_cart_v1', $lines);
        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);
        return new CartService($stack);
    }

    /** @return array{line_id: string, tier_id: string, quantity: int, donor_name: null, custom_price_cents: int|null} */
    private function rawLine(string $id, string $tier = 'begonia_unit', int $quantity = 3, ?int $customPrice = null): array
    {
        return ['line_id' => $id, 'tier_id' => $tier, 'quantity' => $quantity,
            'donor_name' => null, 'custom_price_cents' => $customPrice];
    }

    public function testManyMixedLinesPreserveUnitFlatAndCustomTotals(): void
    {
        $lines = [];
        for ($i = 0; $i < 100; ++$i) {
            $lines[] = $this->rawLine('unit-'.$i);
            $lines[] = $this->rawLine('flat-'.$i, 'vip_20', 7);
            $lines[] = $this->rawLine('custom-'.$i, 'free_donation', 2, 123);
        }
        self::assertSame(100 * (300 + 2000 + 246), $this->serviceWithLines($lines)->totalCents($this->catalog()));
    }

    public function testDuplicateIdsKeepFirstAmountAndIgnoreLaterUnknownTier(): void
    {
        $cart = $this->serviceWithLines([
            $this->rawLine('same'),
            $this->rawLine('other', 'vip_20', 5),
            $this->rawLine('same', 'unknown', 999),
        ]);
        $catalog = $this->catalog();
        self::assertSame(2600, $cart->totalCents($catalog));
        self::assertSame(300, $cart->getLineTotal('same', $catalog));
    }

    public function testMalformedLateDuplicateIsStillRejected(): void
    {
        $cart = $this->serviceWithLines([$this->rawLine('same'), ['line_id' => 'same', 'quantity' => 'invalid']]);
        $this->expectException(\UnexpectedValueException::class);
        $cart->totalCents($this->catalog());
    }

    public function testUnknownTierInFirstOccurrenceIsRejectedEvenWithCustomAmount(): void
    {
        $cart = $this->serviceWithLines([$this->rawLine('first', 'unknown', 1, 100)]);
        $this->expectException(\InvalidArgumentException::class);
        $cart->totalCents($this->catalog());
    }

    public function testTotalReadsSessionOnceAndObservesLaterMutation(): void
    {
        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\SessionInterface::class);
        $session->expects(self::exactly(2))->method('get')->with('participation_cart_v1', null)
            ->willReturnOnConsecutiveCalls(
                [$this->rawLine('first'), $this->rawLine('second', 'vip_20', 8)],
                [$this->rawLine('first', 'begonia_unit', 4)],
            );
        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);
        $cart = new CartService($stack);
        $catalog = $this->catalog();
        self::assertSame(2300, $cart->totalCents($catalog));
        self::assertSame(400, $cart->totalCents($catalog));
    }

}
