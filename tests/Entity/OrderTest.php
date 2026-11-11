<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testPendingFailedAndPaidTransitions(): void
    {
        $order = new Order();
        self::assertSame(OrderStatus::Pending, $order->getStatus());
        self::assertNotNull($order->getCreatedAt());
        self::assertNull($order->getPaidAt());
        $order->markAsFailed();
        self::assertTrue($order->isFailed());
        $order->markAsPaid();
        self::assertTrue($order->isPaid());
        self::assertNotNull($order->getPaidAt());
    }
}
