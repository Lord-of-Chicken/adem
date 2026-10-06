<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\StripeProcessedEvent;
use App\Repository\OrderRepository;
use App\Service\StripeWebhookHandler;
use App\Tests\Support\BackendWebTestCase;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Event;

final class StripeWebhookFailureTest extends BackendWebTestCase
{
    public function testFlushFailureRollsBackClaimAndPaymentThenRealRetrySucceeds(): void
    {
        $order = (new Order())->setTotalCents(100)->setStripeCheckoutSessionId('cs_flush_failure');
        $this->em->persist($order);
        $this->em->flush();
        $event = Event::constructFrom(['id' => 'evt_flush_failure', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'object' => 'checkout.session', 'id' => 'cs_flush_failure', 'amount_total' => 100, 'currency' => 'eur', 'payment_status' => 'paid',
        ]]]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($this->em->getConnection());
        $em->method('refresh')->willReturnCallback(function (object $entity, LockMode $lock): void { $this->em->refresh($entity, $lock); });
        $em->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('fixture interrupted write'));
        $em->expects(self::once())->method('clear')->willReturnCallback(function (): void { $this->em->clear(); });
        $orders = self::service(OrderRepository::class);
        $handler = new StripeWebhookHandler($em, $orders, $this->createStub(LoggerInterface::class));
        self::assertSame(503, $handler->handle($event));
        self::assertSame(0, $this->em->getRepository(StripeProcessedEvent::class)->count(['stripeEventId' => 'evt_flush_failure']));
        $order = $this->reload($order);
        self::assertFalse($order->isPaid());
        self::assertSame(200, self::service(StripeWebhookHandler::class)->handle($event));
        $order = $this->reload($order);
        self::assertTrue($order->isPaid());
        self::assertSame(1, $this->em->getRepository(StripeProcessedEvent::class)->count(['stripeEventId' => 'evt_flush_failure']));
    }
}
