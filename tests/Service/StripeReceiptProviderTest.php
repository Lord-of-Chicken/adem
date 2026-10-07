<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\StripePaymentService;
use App\Service\StripeReceiptProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;

final class StripeReceiptProviderTest extends TestCase
{
    public function testRetrievesVerifiedReceiptWithoutExposingRawCardOrPayload(): void
    {
        $receipt = $this->provider()->fetch($this->order());
        self::assertSame('https://pay.stripe.com/receipts/payment/fixture', $receipt['receipt_url']);
        self::assertSame('Jean', $receipt['customer_name']);
        self::assertSame('jean@example.test', $receipt['customer_email']);
        self::assertSame('Rue des Fleurs 1, 1180, Uccle, BE', $receipt['customer_address']);
        self::assertSame('visa •••• 4242', $receipt['payment_method']);
        self::assertFalse($receipt['livemode']);
        self::assertSame(1234, $receipt['amount_cents']);
        self::assertSame([['title' => 'Jardinière VIP', 'quantity' => 1, 'unit_amount_cents' => 1234, 'total_cents' => 1234, 'donor_name' => 'Jean']], $receipt['lines']);
        $json = json_encode($receipt, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('4242424242424242', $json);
        self::assertStringNotContainsString('secret_fixture', $json);
        self::assertArrayNotHasKey('billing_details', $receipt);
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('inconsistentPayments')]
    public function testRejectsInconsistentPayment(string $object, array $change): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->provider($object, $change)->fetch($this->order());
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function inconsistentPayments(): iterable
    {
        yield 'unpaid session' => ['session', ['payment_status' => 'unpaid']];
        yield 'different session' => ['session', ['id' => 'cs_other']];
        yield 'different intent' => ['session', ['payment_intent' => 'pi_other']];
        yield 'wrong session amount' => ['session', ['amount_total' => 1235]];
        yield 'wrong currency' => ['intent', ['currency' => 'usd']];
        yield 'partial payment' => ['intent', ['amount_received' => 1200]];
        yield 'pending intent' => ['intent', ['status' => 'processing']];
        yield 'different charge' => ['intent', ['latest_charge' => 'ch_other']];
        yield 'uncaptured charge' => ['charge', ['captured' => false]];
        yield 'partial captured amount' => ['charge', ['amount_captured' => 1200]];
        yield 'failed charge' => ['charge', ['status' => 'failed']];
        yield 'different charge intent' => ['charge', ['payment_intent' => 'pi_other']];
        yield 'mixed test live' => ['charge', ['livemode' => true]];
        yield 'receipt not yet available' => ['charge', ['receipt_url' => null]];
        yield 'hostile receipt link' => ['charge', ['receipt_url' => 'https://pay.stripe.com.evil.test/receipt']];
        yield 'incomplete lines' => ['session', ['line_items' => ['has_more' => true, 'data' => []]]];
        yield 'missing lines' => ['session', ['line_items' => null]];
        yield 'line total mismatch' => ['session', ['line_items' => ['has_more' => false, 'data' => [['quantity' => 1, 'amount_total' => 999, 'currency' => 'eur', 'description' => 'Fixture']]]]];
        yield 'line invalid quantity' => ['session', ['line_items' => ['has_more' => false, 'data' => [['quantity' => 0, 'amount_total' => 1234, 'currency' => 'eur', 'description' => 'Fixture']]]]];
        yield 'line wrong currency' => ['session', ['line_items' => ['has_more' => false, 'data' => [['quantity' => 1, 'amount_total' => 1234, 'currency' => 'usd', 'description' => 'Fixture']]]]];
        yield 'line missing title' => ['session', ['line_items' => ['has_more' => false, 'data' => [['quantity' => 1, 'amount_total' => 1234, 'currency' => 'eur']]]]];
    }

    public function testFallsBackToBillingDetailsAndDoesNotExposeInvalidCardDigits(): void
    {
        $receipt = $this->provider('session', ['customer_details' => null])->fetch($this->order());
        self::assertSame('Billing Jean', $receipt['customer_name']);
        self::assertSame('billing@example.test', $receipt['customer_email']);
        self::assertSame('Belgique, BE', $receipt['customer_address']);
        $receipt = $this->provider('charge', ['payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242424242424242']]])->fetch($this->order());
        self::assertSame('card', $receipt['payment_method']);
    }

    public function testAcceptsExpandedIntentReferencesAndDescriptionWithoutProduct(): void
    {
        $receipt = $this->provider('session', ['payment_intent' => ['id' => 'pi_fixture', 'object' => 'payment_intent'],
            'line_items' => ['has_more' => false, 'data' => [['quantity' => 1, 'amount_total' => 1234, 'currency' => 'eur', 'description' => 'Historical label']]],
        ])->fetch($this->order());
        self::assertIsArray($receipt['lines']);
        self::assertSame([['title' => 'Historical label', 'quantity' => 1, 'unit_amount_cents' => null, 'total_cents' => 1234, 'donor_name' => null]], $receipt['lines']);
    }

    public function testRejectsUnpaidOrderWithoutCallingStripe(): void
    {
        $stripe = $this->createMock(StripePaymentService::class);
        $stripe->expects(self::never())->method('getPaymentReceiptSession');
        $this->expectException(\UnexpectedValueException::class);
        (new StripeReceiptProvider($stripe))->fetch($this->order()->setStatus(OrderStatus::Pending));
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsNonOfficialOrUnsafeReceiptUrls(mixed $url): void
    {
        self::assertFalse(StripeReceiptProvider::isReceiptUrl($url));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidUrls(): iterable
    {
        yield 'missing' => [null];
        yield 'script' => ['javascript:alert(1)'];
        yield 'http' => ['http://pay.stripe.com/receipt'];
        yield 'deceptive host' => ['https://pay.stripe.com.evil.test/receipt'];
        yield 'user credentials' => ['https://attacker@pay.stripe.com/receipt'];
        yield 'nonstandard port' => ['https://pay.stripe.com:8443/receipt'];
        yield 'no path' => ['https://pay.stripe.com'];
    }

    private function order(): Order
    {
        return (new Order())->setStripeCheckoutSessionId('cs_fixture')->setStripePaymentIntentId('pi_fixture')
            ->setTotalCents(1234)->setStatus(OrderStatus::Paid);
    }

    /** @param array<string, mixed> $change */
    private function provider(?string $changedObject = null, array $change = []): StripeReceiptProvider
    {
        $objects = [
            'session' => [
                'id' => 'cs_fixture', 'payment_intent' => 'pi_fixture', 'payment_status' => 'paid', 'amount_total' => 1234,
                'currency' => 'eur', 'livemode' => false,
                'customer_details' => ['name' => 'Jean', 'email' => 'jean@example.test', 'address' => ['line1' => 'Rue des Fleurs 1', 'postal_code' => '1180', 'city' => 'Uccle', 'country' => 'BE']],
                'line_items' => ['object' => 'list', 'has_more' => false, 'data' => [[
                    'object' => 'item', 'quantity' => 1, 'amount_total' => 1234, 'currency' => 'eur',
                    'price' => ['object' => 'price', 'unit_amount' => 1234, 'product' => ['object' => 'product', 'name' => 'Jardinière VIP', 'metadata' => ['donor_name' => 'Jean']]],
                ]]],
            ],
            'intent' => ['id' => 'pi_fixture', 'status' => 'succeeded', 'amount' => 1234, 'amount_received' => 1234, 'currency' => 'eur', 'latest_charge' => 'ch_fixture', 'livemode' => false, 'client_secret' => 'secret_fixture'],
            'charge' => ['id' => 'ch_fixture', 'payment_intent' => 'pi_fixture', 'paid' => true, 'status' => 'succeeded', 'captured' => true, 'amount' => 1234, 'amount_captured' => 1234, 'currency' => 'eur', 'livemode' => false,
                'receipt_url' => 'https://pay.stripe.com/receipts/payment/fixture',
                'billing_details' => ['name' => 'Billing Jean', 'email' => 'billing@example.test', 'address' => ['line1' => 'Belgique', 'country' => 'BE']],
                'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242', 'number' => '4242424242424242']],
            ],
        ];
        if ($changedObject !== null) {
            $objects[$changedObject] = array_replace($objects[$changedObject], $change);
        }
        $stripe = $this->createStub(StripePaymentService::class);
        $stripe->method('getPaymentReceiptSession')->willReturn(Session::constructFrom($objects['session']));
        $stripe->method('getPaymentIntent')->willReturn(PaymentIntent::constructFrom($objects['intent']));
        $stripe->method('getPaymentReceiptCharge')->willReturn(Charge::constructFrom($objects['charge']));
        return new StripeReceiptProvider($stripe);
    }
}
