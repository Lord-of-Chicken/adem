<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;

/**
 * Service for interacting with Stripe payment API.
 */
class StripePaymentService
{
    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly LoggerInterface $logger,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * @param array<int|string, array{adjustable_quantity?: array{enabled: bool, maximum?: int, minimum?: int}, dynamic_tax_rates?: array<int|string, string>, metadata?: array<string, string>, price?: string, price_data?: array{currency: string, product?: string, product_data?: array{description?: string, images?: array<int|string, string>, metadata?: array<string, string>, name: string, tax_code?: string, unit_label?: string}, recurring?: array{interval: string, interval_count?: int}, tax_behavior?: string, unit_amount?: int, unit_amount_decimal?: string}, quantity?: int, tax_rates?: array<int|string, string>}> $lineItems
     * @param array<string, string> $metadata
     */
    public function createCheckoutSession(array $lineItems, ?string $customerEmail = null, array $metadata = [], ?string $successUrl = null, ?string $cancelUrl = null): Session
    {
        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'metadata' => $metadata,
        ];

        if ($customerEmail) {
            $sessionData['customer_email'] = $customerEmail;
        }

        if ($successUrl) {
            $sessionData['success_url'] = $successUrl;
        }

        if ($cancelUrl) {
            $sessionData['cancel_url'] = $cancelUrl;
        }

        return Session::create($sessionData);
    }

    /**
     * Retrieves a Stripe checkout session by ID.
     *
     * @param string $sessionId The session ID
     * @return Session The Stripe session
     */
    public function getSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }

    /**
     * Retrieves a Stripe payment intent by ID.
     *
     * @param string $paymentIntentId The payment intent ID
     * @return PaymentIntent The payment intent
     */
    public function getPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return PaymentIntent::retrieve($paymentIntentId);
    }

    /**
     * Checks if a Stripe checkout session is paid.
     *
     * @param string $sessionId The session ID
     * @return bool True if the session is paid
     */
    public function isSessionPaid(string $sessionId): bool
    {
        try {
            $session = $this->getSession($sessionId);
            return ($session->payment_status ?? null) === 'paid';
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe session retrieval failed', ['sessionId' => $sessionId, 'exception' => $e]);
            return false;
        }
    }

    /**
     * @return PaymentIntent[]
     */
    public function listAllPaymentIntents(int $limit = 100): array
    {
        return PaymentIntent::all(['limit' => $limit])->data;
    }

    /**
     * @return PaymentIntent[]
     */
    public function listPaymentIntentsByCustomer(string $customerId, int $limit = 100): array
    {
        return PaymentIntent::all([
            'customer' => $customerId,
            'limit' => $limit
        ])->data;
    }

    /**
     * @param array<string, string> $metadata
     * @return PaymentIntent[]
     */
    public function listPaymentIntentsByMetadata(array $metadata, int $limit = 100): array
    {
        $paymentIntents = PaymentIntent::all(['limit' => $limit])->data;

        if ($metadata === []) {
            return $paymentIntents;
        }

        return array_values(array_filter($paymentIntents, static function (PaymentIntent $pi) use ($metadata): bool {
            foreach ($metadata as $key => $value) {
                if (!isset($pi->metadata[$key]) || $pi->metadata[$key] !== $value) {
                    return false;
                }
            }
            return true;
        }));
    }
}
