<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/** SMTP is outside the payment transaction. The outbox remains retryable on failure. */
final readonly class PaymentReceiptSender
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orders,
        private StripeReceiptProvider $provider,
        private PaymentReceiptEmailFactory $emails,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(bool:PAYMENT_RECEIPTS_SEND_ENABLED)%')]
        private bool $enabled,
    ) {}

    public function isEnabled(): bool { return $this->enabled; }

    public function sendForOrder(int $orderId): bool
    {
        if (!$this->enabled) { return false; }
        $db = $this->entityManager->getConnection();
        // Never send an email for an uncommitted payment, including caller-owned transactions.
        if ($db->isTransactionActive()) { return false; }
        $db->beginTransaction();
        try {
            $receipt = $db->fetchAssociative('SELECT id, sent_at FROM payment_receipt WHERE order_id = ? FOR UPDATE', [$orderId]);
            if ($receipt === false || $receipt['sent_at'] !== null) {
                $db->commit();
                return true;
            }
            $order = $this->orders->find($orderId);
            if ($order === null) { throw new \LogicException('Receipt order missing.'); }
            $this->entityManager->refresh($order);
            if (!$order->isPaid()) { throw new \LogicException('Receipt requires a paid order.'); }
            $details = $this->provider->fetch($order);
            $this->mailer->send($this->emails->create($order, $details));
            $db->executeStatement('UPDATE payment_receipt SET sent_at = ?, attempts = attempts + 1, last_error_class = NULL WHERE id = ?', [(new \DateTimeImmutable())->format('Y-m-d H:i:s'), $receipt['id']]);
            $db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            $db->executeStatement('UPDATE payment_receipt SET attempts = attempts + 1, last_error_class = ? WHERE order_id = ? AND sent_at IS NULL', [$exception::class, $orderId]);
            $this->logger->error('Site payment receipt remains pending', ['order_id' => $orderId, 'error_class' => $exception::class]);
            return false;
        }
    }

    /** @return array{sent: int, pending: int} */
    public function sendPending(int $limit = 50): array
    {
        if ($limit < 1 || $limit > 500) { throw new \InvalidArgumentException('Receipt batch limit must be between 1 and 500.'); }
        $ids = $this->entityManager->getConnection()->fetchFirstColumn('SELECT order_id FROM payment_receipt WHERE sent_at IS NULL ORDER BY id ASC LIMIT '.$limit);
        $sent = 0;
        foreach ($ids as $id) {
            $orderId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($orderId)) { throw new \UnexpectedValueException('Invalid receipt order ID.'); }
            if ($this->sendForOrder($orderId)) { ++$sent; }
        }
        return ['sent' => $sent, 'pending' => count($ids) - $sent];
    }
}
