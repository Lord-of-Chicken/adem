<?php

namespace App\Service;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;

class StripePaymentService
{
    public function __construct(
        private readonly string $stripeSecretKey,
    ) {
    }

    public function createCheckoutSession(array $lineItems, ?string $customerEmail = null, array $metadata = [], ?string $successUrl = null, ?string $cancelUrl = null): Session
    {
        Stripe::setApiKey($this->stripeSecretKey);

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

    public function getSession(string $sessionId): Session
    {
        Stripe::setApiKey($this->stripeSecretKey);
        return Session::retrieve($sessionId);
    }

    public function getPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        Stripe::setApiKey($this->stripeSecretKey);
        return PaymentIntent::retrieve($paymentIntentId);
    }

    public function isSessionPaid(string $sessionId): bool
    {
        try {
            $session = $this->getSession($sessionId);
            return $session->payment_status === 'paid';
        } catch (\Exception $e) {
            return false;
        }
    }

    public function listAllPaymentIntents(int $limit = 100): array
    {
        Stripe::setApiKey($this->stripeSecretKey);
        return PaymentIntent::all(['limit' => $limit])->data;
    }

    public function listPaymentIntentsByCustomer(string $customerId, int $limit = 100): array
    {
        Stripe::setApiKey($this->stripeSecretKey);
        return PaymentIntent::all([
            'customer' => $customerId,
            'limit' => $limit
        ])->data;
    }

    public function listPaymentIntentsByMetadata(array $metadata, int $limit = 100): array
    {
        Stripe::setApiKey($this->stripeSecretKey);
        return PaymentIntent::all([
            'limit' => $limit
        ])->data;
    }
}
