<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Command\SendPaymentReceiptsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use App\Entity\PaymentReceipt;
use App\Repository\OrderRepository;
use App\Service\PaymentReceiptEmailFactory;
use App\Service\PaymentReceiptSender;
use App\Service\StripeReceiptProvider;
use App\Tests\Support\BackendWebTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportException;

final class PaymentReceiptDeliveryTest extends BackendWebTestCase
{
    /** @var list<Order> */
    private array $fixtures = [];

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) { $this->em->getConnection()->rollBack(); }
        foreach ($this->fixtures as $order) { $this->em->remove($order); }
        $this->em->flush();
        parent::tearDown();
    }

    private function pending(): Order
    {
        $this->em->getConnection()->rollBack();
        $order = (new Order())->setStripeCheckoutSessionId('cs_receipt_'.bin2hex(random_bytes(8)))
            ->setStripePaymentIntentId('pi_receipt')->setTotalCents(100);
        $order->markAsPaid();
        $this->em->persist($order);
        $this->em->persist(new PaymentReceipt($order));
        $this->em->flush();
        $this->fixtures[] = $order;
        return $order;
    }

    private function sender(MailerInterface $mailer, bool $enabled = true): PaymentReceiptSender
    {
        $provider = $this->createStub(StripeReceiptProvider::class);
        $provider->method('fetch')->willReturn([
            'receipt_url' => 'https://pay.stripe.com/receipts/test-fixture',
            'charge_id' => 'ch_fixture', 'payment_intent_id' => 'pi_receipt', 'session_id' => 'cs_fixture',
            'customer_name' => null, 'customer_email' => null, 'customer_address' => null,
            'payment_method' => 'Visa •••• 4242', 'livemode' => false, 'amount_cents' => 100, 'currency' => 'eur',
            'lines' => [['title' => 'Bégonia', 'quantity' => 1, 'unit_amount_cents' => 100, 'total_cents' => 100, 'donor_name' => null]],
        ]);
        return new PaymentReceiptSender($this->em, self::service(OrderRepository::class), $provider,
            new PaymentReceiptEmailFactory('info@ruelledadem.com'), $mailer, new NullLogger(), $enabled);
    }

    public function testDeliveryIsSentOnceAndNeverInsidePaymentTransaction(): void
    {
        $order = $this->pending();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        $sender = $this->sender($mailer);
        $id = $order->getId();
        self::assertIsInt($id);
        $this->em->getConnection()->beginTransaction();
        self::assertFalse($sender->sendForOrder($id));
        $this->em->getConnection()->rollBack();
        self::assertTrue($sender->sendForOrder($id));
        self::assertTrue($sender->sendForOrder($id));
        $row = $this->em->getConnection()->fetchAssociative('SELECT sent_at, attempts, last_error_class FROM payment_receipt WHERE order_id = ?', [$id]);
        self::assertIsArray($row);
        self::assertNotNull($row['sent_at']);
        self::assertContains($row['attempts'], [1, '1']);
        self::assertNull($row['last_error_class']);
    }

    public function testMailFailureKeepsPaidOrderAndCanBeRetried(): void
    {
        $order = $this->pending();
        $mailer = $this->createMock(MailerInterface::class);
        $attempt = 0;
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(static function () use (&$attempt): void {
            if (++$attempt === 1) { throw new TransportException('Fixture transport unavailable'); }
        });
        $sender = $this->sender($mailer);
        $id = $order->getId();
        self::assertIsInt($id);
        self::assertFalse($sender->sendForOrder($id));
        self::assertTrue($order->isPaid());
        $row = $this->em->getConnection()->fetchAssociative('SELECT sent_at, attempts, last_error_class FROM payment_receipt WHERE order_id = ?', [$id]);
        self::assertIsArray($row);
        self::assertNull($row['sent_at']);
        self::assertSame(TransportException::class, $row['last_error_class']);
        self::assertTrue($sender->sendForOrder($id));
        self::assertSame(['sent' => 0, 'pending' => 0], $sender->sendPending());
    }

    public function testRetryCommandReportsDisabledDeliveryInsteadOfFalseSuccess(): void
    {
        $command = new CommandTester(new SendPaymentReceiptsCommand(self::service(PaymentReceiptSender::class)));
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('Receipt delivery is disabled', $command->getDisplay());
    }

    public function testDisabledDeliveryDoesNotSendOrMarkSent(): void
    {
        $order = $this->pending();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $id = $order->getId();
        self::assertIsInt($id);
        self::assertFalse($this->sender($mailer, false)->sendForOrder($id));
        self::assertNull($this->em->getConnection()->fetchOne('SELECT sent_at FROM payment_receipt WHERE order_id = ?', [$id]));
    }
}
