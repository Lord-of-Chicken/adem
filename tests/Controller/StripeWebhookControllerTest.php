<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\StripeProcessedEvent;
use App\Tests\Support\BackendWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class StripeWebhookControllerTest extends BackendWebTestCase
{
    private const SECRET = 'whsec_audit_fixture';

    /** @param array<string, mixed> $object */
    private function deliver(string $eventId, string $type, array $object, bool $validSignature = true): void
    {
        $payload = json_encode(['id' => $eventId, 'object' => 'event', 'type' => $type,
            'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $validSignature ? self::SECRET : 'wrong');
        $this->client->request('POST', '/stripe/webhook', [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function order(): Order
    {
        $order = (new Order())->setStripeCheckoutSessionId('cs_audit')->setTotalCents(2000)->setCartData([]);
        $this->em->persist($order);
        $this->em->flush();
        return $order;
    }

    /** @return array<string, mixed> */
    private function paid(): array
    {
        return ['object' => 'checkout.session', 'id' => 'cs_audit', 'payment_status' => 'paid',
            'amount_total' => 2000, 'currency' => 'eur', 'payment_intent' => 'pi_audit'];
    }

    public function testSignedRootWebhookPaysOnceAndFailureCannotUndoPayment(): void
    {
        $order = $this->order();
        $this->deliver('evt_audit_paid', 'checkout.session.completed', $this->paid());
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
        $paidAt = $order->getPaidAt();
        self::assertSame('pi_audit', $order->getStripePaymentIntentId());
        $this->deliver('evt_audit_paid', 'checkout.session.completed', $this->paid());
        self::assertResponseStatusCodeSame(200);
        $this->deliver('evt_audit_failed', 'payment_intent.payment_failed', ['object' => 'payment_intent', 'id' => 'pi_audit']);
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
        self::assertEquals($paidAt, $order->getPaidAt());
        self::assertSame(2, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
    }

    public function testWrongSignatureDoesNotChangeOrderOrClaimEvent(): void
    {
        $order = $this->order();
        $this->deliver('evt_wrong', 'checkout.session.completed', $this->paid(), false);
        self::assertResponseStatusCodeSame(400);
        $order = $this->reload($order);
        self::assertFalse($order->isPaid());
        self::assertSame(0, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function mismatches(): iterable
    {
        yield 'wrong amount' => [100, 'eur'];
        yield 'wrong currency' => [2000, 'usd'];
        yield 'missing amount' => [null, 'eur'];
        yield 'string amount' => ['2000', 'eur'];
    }

    #[DataProvider('mismatches')]
    public function testMismatchesRemainRetryable(mixed $amount, string $currency): void
    {
        $order = $this->order();
        $object = $this->paid();
        $object['amount_total'] = $amount;
        $object['currency'] = $currency;
        $this->deliver('evt_mismatch', 'checkout.session.completed', $object);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
        $order = $this->reload($order);
        self::assertFalse($order->isPaid());
        $this->deliver('evt_mismatch', 'checkout.session.completed', $this->paid());
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
    }

    public function testDeliveryBeforeOrderCreationCanBeRetried(): void
    {
        $this->deliver('evt_early', 'checkout.session.completed', $this->paid());
        self::assertResponseStatusCodeSame(503);
        self::assertSame(0, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
        $order = $this->order();
        $this->deliver('evt_early', 'checkout.session.completed', $this->paid());
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
    }

    public function testExpiredThenPaidAndUnpaidDelivery(): void
    {
        $order = $this->order();
        $this->deliver('evt_expired', 'checkout.session.expired', ['object' => 'checkout.session', 'id' => 'cs_audit']);
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isFailed());
        $unpaid = $this->paid();
        $unpaid['payment_status'] = 'unpaid';
        $this->deliver('evt_unpaid', 'checkout.session.completed', $unpaid);
        self::assertResponseStatusCodeSame(503);
        $this->deliver('evt_late_paid', 'checkout.session.completed', $this->paid());
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
    }

    public function testMalformedAndUnsupportedEvents(): void
    {
        $this->client->request('POST', '/stripe/webhook', [], [], [], '{}');
        self::assertResponseStatusCodeSame(400);
        $this->deliver('evt_other', 'customer.created', ['object' => 'customer', 'id' => 'cus_fixture']);
        self::assertResponseStatusCodeSame(200);
        $this->deliver('evt_shape', 'checkout.session.completed', ['object' => 'payment_intent', 'id' => 'pi_fixture']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
    }
    public function testEarlyPaymentIntentAndLostSessionResponseCanBeCorrelated(): void
    {
        $order = $this->order()->setStripeCheckoutSessionId('draft_fixture');
        $this->em->flush();
        $reference = (string) $order->getId();
        $this->deliver('evt_early_pi', 'payment_intent.payment_failed', [
            'object' => 'payment_intent', 'id' => 'pi_audit', 'metadata' => ['order_id' => $reference],
        ]);
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isFailed());
        self::assertSame('pi_audit', $order->getStripePaymentIntentId());
        $this->deliver('evt_response_lost', 'checkout.session.completed', $this->paid() + ['metadata' => ['order_id' => $reference]]);
        self::assertResponseStatusCodeSame(200);
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
        self::assertSame('cs_audit', $order->getStripeCheckoutSessionId());
        self::assertSame(2, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
    }

    public function testMetadataCannotAttachAnotherSessionOrIntentToExistingOrder(): void
    {
        $order = $this->order()->setStripePaymentIntentId('pi_audit');
        $this->em->flush();
        $reference = (string) $order->getId();
        $other = $this->paid(); $other['id'] = 'cs_other'; $other['metadata'] = ['order_id' => $reference];
        $this->deliver('evt_bad_reference', 'checkout.session.completed', $other);
        self::assertResponseStatusCodeSame(400);
        $this->deliver('evt_bad_intent', 'payment_intent.payment_failed', ['object' => 'payment_intent', 'id' => 'pi_other', 'metadata' => ['order_id' => $reference]]);
        self::assertResponseStatusCodeSame(400);
        self::assertFalse($this->reload($order)->isPaid());
        self::assertSame(0, $this->em->getRepository(StripeProcessedEvent::class)->count([]));
    }

}
