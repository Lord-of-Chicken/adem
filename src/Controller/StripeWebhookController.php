<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StripeWebhookHandler;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class StripeWebhookController
{
    public function __construct(
        private StripeWebhookHandler $handler,
        private LoggerInterface $logger,
        #[Autowire('%stripe_webhook_secret%')]
        private string $webhookSecret,
    ) {}

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $signature = $request->headers->get('Stripe-Signature');
        if (!$signature || !$this->webhookSecret || $this->webhookSecret === 'whsec_CHANGE_ME') {
            return new Response('Invalid webhook configuration or signature.', 400);
        }
        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $this->webhookSecret);
        } catch (\UnexpectedValueException | SignatureVerificationException $exception) {
            $this->logger->warning('Stripe webhook rejected', ['error_class' => $exception::class]);
            return new Response('Invalid webhook.', 400);
        }
        return new Response('Webhook processed.', $this->handler->handle($event));
    }
}
