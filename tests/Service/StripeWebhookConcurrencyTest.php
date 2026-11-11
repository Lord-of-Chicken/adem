<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\StripeProcessedEvent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class StripeWebhookConcurrencyTest extends KernelTestCase
{
    /** @return iterable<string, array{bool}> */
    public static function races(): iterable
    {
        yield 'same event delivered twice' => [true];
        yield 'paid event racing expiry' => [false];
    }

    #[DataProvider('races')]
    public function testConcurrentDeliveryKeepsOnePaymentAndPaidIsTerminal(bool $duplicate): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertStringContainsString('_test', $em->getConnection()->getDatabase() ?? '');
        $suffix = bin2hex(random_bytes(8));
        $sessionId = 'cs_concurrent_'.$suffix;
        $eventId = 'evt_concurrent_'.$suffix;
        $expiryId = $eventId.'_expiry';
        $order = (new Order())->setTotalCents(100)->setStripeCheckoutSessionId($sessionId);
        $em->persist($order);
        $em->flush();
        $barrier = sys_get_temp_dir().'/adem-barrier-'.$suffix;
        $processes = [];
        try {
            foreach ([0, 1] as $index) {
                $expiry = !$duplicate && $index === 1;
                $data = ['id' => $expiry ? $expiryId : $eventId, 'type' => $expiry ? 'checkout.session.expired' : 'checkout.session.completed',
                    'data' => ['object' => ['object' => 'checkout.session', 'id' => $sessionId, 'amount_total' => 100, 'currency' => 'eur', 'payment_status' => 'paid']]];
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/webhook-worker.php', $barrier], dirname(__DIR__, 2), ['APP_ENV' => 'test'], json_encode($data, JSON_THROW_ON_ERROR), 20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (count(glob($barrier.'.*.ready') ?: []) !== 2) {
                if (microtime(true) > $deadline) { self::fail('Workers did not reach barrier.'); }
                foreach ($processes as $process) {
                    if (!$process->isRunning()) { self::fail($process->getErrorOutput()); }
                }
                usleep(1000);
            }
            touch($barrier);
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                self::assertSame('200', $process->getOutput());
            }
            $em->refresh($order);
            self::assertTrue($order->isPaid());
            self::assertNotNull($order->getPaidAt());
            self::assertSame($duplicate ? 1 : 2, $em->getRepository(StripeProcessedEvent::class)->count(['stripeEventId' => [$eventId, $expiryId]]));
        } finally {
            foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(); } }
            foreach (glob($barrier.'*') ?: [] as $file) { unlink($file); }
            $em->getConnection()->executeStatement('DELETE FROM stripe_processed_event WHERE stripe_event_id IN (?, ?)', [$eventId, $expiryId]);
            $em->remove($order);
            $em->flush();
        }
    }
}
