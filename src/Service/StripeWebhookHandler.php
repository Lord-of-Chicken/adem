<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\PaymentIntent;

/** The event claim and order change commit together; failed processing remains retryable. */
final readonly class StripeWebhookHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orders,
        private LoggerInterface $logger,
    ) {}

    public function handle(Event $event): int
    {
        if (!is_string($event->id) || $event->id === '' || strlen($event->id) > 255) {
            return 400;
        }
        if (!in_array($event->type, ['checkout.session.completed', 'checkout.session.expired', 'payment_intent.payment_failed'], true)) {
            return 200;
        }
        $object = $event->data->object;
        if ((!$object instanceof Session && !$object instanceof PaymentIntent) || !is_string($object->id) || $object->id === '') {
            return 400;
        }
        if (($event->type === 'payment_intent.payment_failed') !== ($object instanceof PaymentIntent)) {
            return 400;
        }
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            // DBAL claim avoids closing the EntityManager when a concurrent delivery wins.
            try {
                $connection->insert('stripe_processed_event', [
                    'stripe_event_id' => $event->id,
                    'processed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            } catch (UniqueConstraintViolationException) {
                $connection->rollBack();
                return 200;
            }
            $order = $object instanceof Session
                ? $this->orders->findByStripeCheckoutSessionId($object->id)
                : $this->orders->findByStripePaymentIntentId($object->id);
            if ($order === null) {
                $metadata = $object->metadata;
                $reference = $metadata instanceof \Stripe\StripeObject ? $metadata->offsetGet('order_id') : null;
                $orderId = is_string($reference)
                    ? filter_var($reference, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
                if (is_int($orderId)) {
                    $order = $this->orders->find($orderId);
                }
            }
            if ($order === null) {
                $connection->rollBack();
                // Stripe may deliver before local checkout persistence; do not acknowledge a lost payment.
                return 503;
            }
            $this->entityManager->refresh($order, LockMode::PESSIMISTIC_WRITE);
            if (($object instanceof Session && $order->getStripeCheckoutSessionId() !== $object->id
                    && !str_starts_with($order->getStripeCheckoutSessionId() ?? '', 'draft_'))
                || ($object instanceof PaymentIntent && $order->getStripePaymentIntentId() !== null
                    && $order->getStripePaymentIntentId() !== $object->id)) {
                $connection->rollBack();
                return 400;
            }
            if ($event->type === 'checkout.session.completed' && $object instanceof Session) {
                if ($object->payment_status !== 'paid') {
                    $connection->rollBack();
                    return 503;
                }
                if (!is_int($object->amount_total) || $object->amount_total !== $order->getTotalCents() || $object->currency !== 'eur') {
                    $connection->rollBack();
                    $this->logger->error('Stripe payment amount or currency mismatch', ['order_id' => $order->getId()]);
                    return 400;
                }
                if (is_string($object->payment_intent)) {
                    $order->setStripePaymentIntentId($object->payment_intent);
                }
                if (!$order->isPaid()) {
                    $order->markAsPaid();
                }
            } elseif (!$order->isPaid()) {
                $order->markAsFailed();
            }
            if ($object instanceof Session) {
                $order->setStripeCheckoutSessionId($object->id);
            } else {
                $order->setStripePaymentIntentId($object->id);
            }
            $this->entityManager->flush();
            $connection->commit();
            return 200;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();
            $this->logger->error('Stripe webhook processing failed; retry required', [
                'event_id' => $event->id, 'error_class' => $exception::class,
            ]);
            return 503;
        }
    }
}
