<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Service\StripePaymentService;
use App\Tests\Support\BackendWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\Checkout\Session;

final class PaymentFlowTest extends BackendWebTestCase
{
    /** @return list<array{line_id: string, tier_id: string, quantity: int, donor_name: null, custom_price_cents: null}> */
    private function lines(): array
    {
        return [['line_id' => 'audit-line', 'tier_id' => 'vip_20', 'quantity' => 5, 'donor_name' => null, 'custom_price_cents' => null]];
    }

    public function testCheckoutChargesFlatPriceAndSnapshotsOrder(): void
    {
        $stripe = $this->createMock(StripePaymentService::class);
        $stripe->expects(self::once())->method('createCheckoutSession')->willReturnCallback(
            /**
             * @param list<array{price_data: array{unit_amount: int}, quantity: int}> $items
             * @param array<string, string> $metadata
             */
            function (array $items, ?string $email, array $metadata, ?string $success, ?string $cancel): Session {
                self::assertIsArray($items[0]);
                self::assertIsArray($items[0]['price_data']);
                self::assertSame(2000, $items[0]['price_data']['unit_amount']);
                self::assertSame(1, $items[0]['quantity']);
                self::assertNull($email);
                self::assertArrayHasKey('order_id', $metadata);
                self::assertTrue(ctype_digit($metadata['order_id']));
                $draft = $this->em->find(Order::class, (int) $metadata['order_id']);
                self::assertNotNull($draft);
                self::assertStringStartsWith('draft_', $draft->getStripeCheckoutSessionId() ?? '');
                self::assertNotNull($success);
                self::assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $success);
                self::assertNotNull($cancel);
                return Session::constructFrom(['id' => 'cs_fixture', 'url' => 'https://checkout.stripe.com/c/fixture']);
            }
        );
        static::getContainer()->set(StripePaymentService::class, $stripe);
        $this->session(['participation_cart_v1' => $this->lines()]);
        $this->client->request('GET', '/fr/checkout');
        self::assertResponseRedirects('https://checkout.stripe.com/c/fixture', 303);
        $order = $this->em->getRepository(Order::class)->findOneBy(['stripeCheckoutSessionId' => 'cs_fixture']);
        self::assertNotNull($order);
        self::assertSame(2000, $order->getTotalCents());
        self::assertSame($this->lines(), $order->getCartData());
        self::assertFalse($order->isPaid());
        $this->client->request('GET', '/fr/checkout');
        self::assertResponseRedirects('https://checkout.stripe.com/c/fixture', 303);
        self::assertSame(1, $this->em->getRepository(Order::class)->count([]));
    }

    public function testCheckoutTransportFailureKeepsCart(): void
    {
        $stripe = $this->createMock(StripePaymentService::class);
        $stripe->expects(self::once())->method('createCheckoutSession')->willThrowException(new \RuntimeException('fixture network failure'));
        static::getContainer()->set(StripePaymentService::class, $stripe);
        $session = $this->session(['participation_cart_v1' => $this->lines()]);
        $this->client->request('GET', '/fr/checkout');
        self::assertResponseRedirects('/fr/panier');
        $session->start();
        self::assertSame($this->lines(), $session->get('participation_cart_v1'));
    }

    public function testEmptyCheckoutDoesNotContactStripe(): void
    {
        $stripe = $this->createMock(StripePaymentService::class);
        $stripe->expects(self::never())->method('createCheckoutSession');
        static::getContainer()->set(StripePaymentService::class, $stripe);
        $this->client->request('GET', '/fr/checkout');
        self::assertResponseRedirects('/fr/panier');
        self::assertSame(0, $this->em->getRepository(Order::class)->count([]));
    }

    /** @return iterable<string, array{bool, bool, bool, bool}> */
    public static function returns(): iterable
    {
        yield 'own paid unchanged cart' => [true, true, false, true];
        yield 'another visitor' => [true, false, false, false];
        yield 'pending payment' => [false, true, false, false];
        yield 'new cart added while paying' => [true, true, true, false];
    }

    #[DataProvider('returns')]
    public function testOnlyOwnPaidUnchangedCartCanBeCleared(bool $paid, bool $own, bool $changed, bool $cleared): void
    {
        $order = (new Order())->setStripeCheckoutSessionId('cs_return')->setTotalCents(2000)->setCartData($this->lines());
        if ($paid) { $order->markAsPaid(); }
        $this->em->persist($order);
        $this->em->flush();
        $lines = $this->lines();
        $values = ['participation_cart_v1' => $lines];
        if ($own) {
            $values['stripe_checkout_snapshot'] = ['session_id' => 'cs_return', 'cart_hash' => hash('sha256', json_encode($lines, JSON_THROW_ON_ERROR))];
        }
        if ($changed) { $values['participation_cart_v1'][0]['quantity'] = 6; }
        $session = $this->session($values);
        $this->client->request('GET', '/fr/payment/success?session_id=cs_return');
        self::assertResponseStatusCodeSame(302);
        $session->start();
        self::assertSame($cleared ? [] : $values['participation_cart_v1'], $session->get('participation_cart_v1', []));
        if ($cleared) {
            $this->client->request('GET', '/fr/payment/success?session_id=cs_return');
            self::assertResponseRedirects('/fr/panier');
        }
    }

    public function testInvalidReturnAndCancelDoNotWriteOrders(): void
    {
        $this->client->request('GET', '/fr/payment/success');
        self::assertResponseRedirects('/fr/panier');
        $this->client->request('GET', '/fr/payment/cancel');
        self::assertResponseRedirects('/fr/panier');
        self::assertSame(0, $this->em->getRepository(Order::class)->count([]));
    }
}
