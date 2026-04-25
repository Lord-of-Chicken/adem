<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $webhookSecret = $this->getParameter('stripe_webhook_secret');

        if (!$sigHeader || !$webhookSecret || $webhookSecret === 'whsec_CHANGE_ME') {
            $this->logger->warning('Stripe webhook: secret non configuré ou signature manquante.');
            return new Response('Webhook secret not configured', 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException) {
            $this->logger->warning('Stripe webhook: payload invalide.');
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            $this->logger->warning('Stripe webhook: signature invalide.');
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $order = $this->orderRepository->findByStripeCheckoutSessionId($session->id);

            if (!$order) {
                $this->logger->info('Stripe webhook: aucune commande pour session ' . $session->id);
                return new Response('No matching order', 200);
            }

            if ($order->isPaid()) {
                return new Response('Already paid', 200);
            }

            if ($session->payment_status === 'paid') {
                $order->markAsPaid();
                $this->entityManager->flush();
                $this->logger->info('Stripe webhook: commande #' . $order->getId() . ' marquée payée.');
            }
        }

        return new Response('OK', 200);
    }
}
