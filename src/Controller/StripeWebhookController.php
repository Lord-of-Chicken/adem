<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StripeProcessedEvent;
use App\Repository\OrderRepository;
use App\Repository\StripeProcessedEventRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles Stripe webhook events for payment status updates.
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly StripeProcessedEventRepository $processedEventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Processes Stripe webhook events.
     *
     * @param Request $request The HTTP request
     * @return Response Success or error response
     */
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        /** @var string */
        $webhookSecret = $this->getParameter('stripe_webhook_secret');

        if (!$sigHeader || !$webhookSecret || $webhookSecret === 'whsec_CHANGE_ME') {
            $this->logger->warning('Stripe webhook: secret non configuré ou signature manquante.');
            return new Response($this->translator->trans('stripe.webhook_secret_not_configured'), 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException) {
            $this->logger->warning('Stripe webhook: payload invalide.');
            return new Response($this->translator->trans('stripe.invalid_payload'), 400);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            $this->logger->warning('Stripe webhook: signature invalide.');
            return new Response($this->translator->trans('stripe.invalid_signature'), 400);
        }

        /** @var string $eventId */
        $eventId = $event->id;

        // Idempotency: if this event was already processed, acknowledge and stop.
        if ($this->processedEventRepository->existsByStripeEventId($eventId)) {
            $this->logger->info('Stripe webhook: event déjà traité ' . $eventId);
            return new Response($this->translator->trans('stripe.ok'), 200);
        }

        // Persist the event marker first. The UNIQUE constraint protects against
        // concurrent deliveries of the same event.
        try {
            $this->processedEventRepository->save(new StripeProcessedEvent($eventId));
        } catch (UniqueConstraintViolationException) {
            $this->logger->info('Stripe webhook: event traité concurremment ' . $eventId);
            return new Response($this->translator->trans('stripe.ok'), 200);
        }

        $response = match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'checkout.session.expired'   => $this->handleSessionExpired($event),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
            default => null,
        };

        return $response ?? new Response($this->translator->trans('stripe.ok'), 200);
    }

    /**
     * Handles the checkout.session.completed event: validates the paid amount
     * against the stored order total before marking the order as paid.
     *
     * @param \Stripe\Event $event The Stripe event
     * @return Response The HTTP response
     */
    private function handleCheckoutCompleted(\Stripe\Event $event): Response
    {
        $session = $event->data->object;

        /** @var string $sessionId */
        $sessionId = $session->id;
        $order = $this->orderRepository->findByStripeCheckoutSessionId($sessionId);

        if (!$order) {
            $this->logger->info('Stripe webhook: aucune commande pour session ' . $sessionId);
            return new Response($this->translator->trans('stripe.no_matching_order'), 200);
        }

        // Capture the payment intent for later failure correlation.
        if (isset($session->payment_intent) && is_string($session->payment_intent)) {
            $order->setStripePaymentIntentId($session->payment_intent);
        }

        if ($order->isPaid()) {
            $this->entityManager->flush();
            return new Response($this->translator->trans('stripe.already_paid'), 200);
        }

        /** @var string $paymentStatus */
        $paymentStatus = $session->payment_status ?? '';
        if ($paymentStatus !== 'paid') {
            $this->entityManager->flush();
            return new Response($this->translator->trans('stripe.ok'), 200);
        }

        // Anti-tampering: the amount actually paid must match the stored total.
        /** @var int|null $amountTotal */
        $amountTotal = $session->amount_total ?? null;
        $stripeAmount = (int) $amountTotal;
        $expectedAmount = $order->getTotalCents();

        if ($stripeAmount !== $expectedAmount) {
            $this->logger->error('Stripe webhook: montant invalide, commande non marquée payée.', [
                'order_id'   => $order->getId(),
                'expected'   => $expectedAmount,
                'stripe'     => $stripeAmount,
                'session_id' => $sessionId,
            ]);
            // Persist the payment intent id even on mismatch, then reject.
            $this->entityManager->flush();
            return new Response($this->translator->trans('stripe.amount_mismatch'), 400);
        }

        $order->markAsPaid();
        $this->entityManager->flush();
        $this->logger->info('Stripe webhook: commande #' . $order->getId() . ' marquée payée.');

        return new Response($this->translator->trans('stripe.ok'), 200);
    }

    /**
     * Handles the checkout.session.expired event: marks the related order failed.
     *
     * @param \Stripe\Event $event The Stripe event
     * @return Response The HTTP response
     */
    private function handleSessionExpired(\Stripe\Event $event): Response
    {
        $session = $event->data->object;

        /** @var string $sessionId */
        $sessionId = $session->id;
        $order = $this->orderRepository->findByStripeCheckoutSessionId($sessionId);

        if ($order && !$order->isPaid()) {
            $order->markAsFailed();
            $this->entityManager->flush();
            $this->logger->info('Stripe webhook: commande #' . $order->getId() . ' marquée échouée (session expirée).');
        }

        return new Response($this->translator->trans('stripe.ok'), 200);
    }

    /**
     * Handles the payment_intent.payment_failed event: marks the related order failed.
     *
     * @param \Stripe\Event $event The Stripe event
     * @return Response The HTTP response
     */
    private function handlePaymentFailed(\Stripe\Event $event): Response
    {
        $paymentIntent = $event->data->object;

        /** @var string $paymentIntentId */
        $paymentIntentId = $paymentIntent->id;
        $order = $this->orderRepository->findByStripePaymentIntentId($paymentIntentId);

        if ($order && !$order->isPaid()) {
            $order->markAsFailed();
            $this->entityManager->flush();
            $this->logger->info('Stripe webhook: commande #' . $order->getId() . ' marquée échouée (paiement refusé).');
        }

        return new Response($this->translator->trans('stripe.ok'), 200);
    }
}
