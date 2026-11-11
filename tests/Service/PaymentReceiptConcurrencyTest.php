<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\PaymentReceipt;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class PaymentReceiptConcurrencyTest extends KernelTestCase
{
    public function testTwoWorkersSendOneReceiptWhileCompetingForTheSameOrder(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $db = $em->getConnection();
        self::assertStringContainsString('_test', $db->getDatabase() ?? '');
        self::assertFalse($db->isTransactionActive(), 'Workers require a committed fixture.');
        $suffix = bin2hex(random_bytes(8));
        $barrier = sys_get_temp_dir().'/adem-receipt-barrier-'.$suffix;
        $order = (new Order())->setTotalCents(1234)->setStripeCheckoutSessionId('cs_receipt_race_'.$suffix)
            ->setStripePaymentIntentId('pi_receipt_race_'.$suffix)->setStatus(OrderStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->persist($order);
        $em->persist(new PaymentReceipt($order));
        $em->flush();
        $orderId = $order->getId();
        self::assertNotNull($orderId);
        $processes = [];
        try {
            foreach ([0, 1] as $unused) {
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/receipt-worker.php', $barrier, (string) $orderId],
                    dirname(__DIR__, 2), ['APP_ENV' => 'test'], null, 20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (count(glob($barrier.'.*.ready') ?: []) !== 2) {
                if (microtime(true) > $deadline) { self::fail('Receipt workers did not reach the barrier.'); }
                foreach ($processes as $process) {
                    if (!$process->isRunning()) { self::fail($process->getErrorOutput()); }
                }
                usleep(1000);
            }
            touch($barrier);
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                self::assertSame('sent', $process->getOutput());
            }
            $deliveries = file($barrier.'.deliveries', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($deliveries);
            self::assertCount(1, $deliveries, 'Two competing workers must not submit two SMTP messages.');
            $message = json_decode($deliveries[0], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($message);
            self::assertSame('site@example.test', $message['to']);
            self::assertIsString($message['text']);
            self::assertStringContainsString('Reçu officiel Stripe', $message['text']);
            $row = $db->fetchAssociative('SELECT sent_at, attempts, last_error_class FROM payment_receipt WHERE order_id = ?', [$orderId]);
            self::assertIsArray($row);
            self::assertNotNull($row['sent_at']);
            self::assertTrue(in_array($row['attempts'], [1, '1'], true));
            self::assertNull($row['last_error_class']);
        } finally {
            foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(); } }
            foreach (glob($barrier.'*') ?: [] as $file) { unlink($file); }
            $db->executeStatement('DELETE FROM payment_receipt WHERE order_id = ?', [$orderId]);
            $em->remove($order);
            $em->flush();
        }
    }
}
