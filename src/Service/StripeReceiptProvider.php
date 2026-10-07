<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;

/** Retrieves only the verified payment data needed for the site's receipt email. */
readonly class StripeReceiptProvider
{
    public function __construct(private StripePaymentService $stripe)
    {
    }

    /** @return array<string, mixed> */
    public function fetch(Order $order): array
    {
        $sessionId = $order->getStripeCheckoutSessionId();
        $intentId = $order->getStripePaymentIntentId();
        $total = $order->getTotalCents();
        if (!$order->isPaid() || $sessionId === null || !str_starts_with($sessionId, 'cs_')
            || $intentId === null || !str_starts_with($intentId, 'pi_') || $total === null || $total <= 0) {
            throw new \UnexpectedValueException('The order has no confirmed Stripe payment.');
        }

        $session = $this->stripe->getPaymentReceiptSession($sessionId)->toArray();
        $intent = $this->stripe->getPaymentIntent($intentId)->toArray();
        $charge = $this->stripe->getPaymentReceiptCharge($intentId)->toArray();
        $chargeId = $charge['id'] ?? null;
        $latestCharge = $intent['latest_charge'] ?? null;
        if (is_array($latestCharge)) {
            $latestCharge = $latestCharge['id'] ?? null;
        }
        $sessionIntent = $session['payment_intent'] ?? null;
        if (is_array($sessionIntent)) {
            $sessionIntent = $sessionIntent['id'] ?? null;
        }
        if (($session['id'] ?? null) !== $sessionId || $sessionIntent !== $intentId
            || ($session['payment_status'] ?? null) !== 'paid' || ($session['amount_total'] ?? null) !== $total
            || ($session['currency'] ?? null) !== 'eur' || ($intent['id'] ?? null) !== $intentId
            || ($intent['status'] ?? null) !== 'succeeded' || ($intent['amount'] ?? null) !== $total
            || ($intent['amount_received'] ?? null) !== $total || ($intent['currency'] ?? null) !== 'eur'
            || !is_string($chargeId) || !str_starts_with($chargeId, 'ch_') || $latestCharge !== $chargeId
            || ($charge['payment_intent'] ?? null) !== $intentId || ($charge['paid'] ?? null) !== true
            || ($charge['status'] ?? null) !== 'succeeded' || ($charge['captured'] ?? null) !== true
            || ($charge['amount'] ?? null) !== $total || ($charge['amount_captured'] ?? null) !== $total
            || ($charge['currency'] ?? null) !== 'eur' || !is_bool($charge['livemode'] ?? null)
            || ($charge['livemode'] ?? null) !== ($intent['livemode'] ?? null)
            || ($charge['livemode'] ?? null) !== ($session['livemode'] ?? null)) {
            throw new \UnexpectedValueException('Stripe payment details do not match the paid order.');
        }
        $receiptUrl = $charge['receipt_url'] ?? null;
        if (!self::isReceiptUrl($receiptUrl)) {
            throw new \UnexpectedValueException('The Stripe receipt is not yet available or has an invalid URL.');
        }

        $collection = $session['line_items'] ?? null;
        if (!is_array($collection) || ($collection['has_more'] ?? null) !== false || !is_array($collection['data'] ?? null)) {
            throw new \UnexpectedValueException('Complete Stripe payment lines are required.');
        }
        $lines = [];
        $lineTotal = 0;
        foreach ($collection['data'] as $item) {
            if (!is_array($item) || !is_int($item['quantity'] ?? null) || $item['quantity'] <= 0
                || !is_int($item['amount_total'] ?? null) || $item['amount_total'] < 0
                || ($item['currency'] ?? null) !== 'eur') {
                throw new \UnexpectedValueException('Invalid Stripe payment line.');
            }
            $price = is_array($item['price'] ?? null) ? $item['price'] : [];
            $product = is_array($price['product'] ?? null) ? $price['product'] : [];
            $metadata = is_array($product['metadata'] ?? null) ? $product['metadata'] : [];
            $title = self::optionalString($product['name'] ?? null) ?? self::optionalString($item['description'] ?? null);
            if ($title === null) {
                throw new \UnexpectedValueException('The Stripe payment line has no description.');
            }
            $lines[] = [
                'title' => $title,
                'quantity' => $item['quantity'],
                'unit_amount_cents' => is_int($price['unit_amount'] ?? null) ? $price['unit_amount'] : null,
                'total_cents' => $item['amount_total'],
                'donor_name' => self::optionalString($metadata['donor_name'] ?? null),
            ];
            $lineTotal += $item['amount_total'];
        }
        if ($lines === [] || $lineTotal !== $total) {
            throw new \UnexpectedValueException('Stripe payment lines do not match the paid total.');
        }

        $customer = is_array($session['customer_details'] ?? null) ? $session['customer_details'] : [];
        $billing = is_array($charge['billing_details'] ?? null) ? $charge['billing_details'] : [];
        $address = is_array($customer['address'] ?? null) ? $customer['address'] : ($billing['address'] ?? null);
        $addressParts = [];
        if (is_array($address)) {
            foreach (['line1', 'line2', 'postal_code', 'city', 'state', 'country'] as $field) {
                $part = self::optionalString($address[$field] ?? null);
                if ($part !== null) {
                    $addressParts[] = $part;
                }
            }
        }
        $method = is_array($charge['payment_method_details'] ?? null) ? $charge['payment_method_details'] : [];
        $card = is_array($method['card'] ?? null) ? $method['card'] : [];
        $brand = self::optionalString($card['brand'] ?? null);
        $last4 = self::optionalString($card['last4'] ?? null);
        $paymentMethod = self::optionalString($method['type'] ?? null) ?? 'Non renseigné';
        if ($brand !== null && $last4 !== null && preg_match('/^\d{4}$/D', $last4) === 1) {
            $paymentMethod = $brand.' •••• '.$last4;
        }

        return [
            'receipt_url' => $receiptUrl,
            'charge_id' => $chargeId,
            'payment_intent_id' => $intentId,
            'session_id' => $sessionId,
            'customer_name' => self::optionalString($customer['name'] ?? null) ?? self::optionalString($billing['name'] ?? null),
            'customer_email' => self::optionalString($customer['email'] ?? null) ?? self::optionalString($billing['email'] ?? null),
            'customer_address' => $addressParts === [] ? null : implode(', ', $addressParts),
            'payment_method' => $paymentMethod,
            'livemode' => $charge['livemode'],
            'amount_cents' => $total,
            'currency' => 'eur',
            'lines' => $lines,
        ];
    }

    public static function isReceiptUrl(mixed $url): bool
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, ['pay.stripe.com', 'receipts.stripe.com'], true)
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['port']) && is_string($parts['path'] ?? null) && $parts['path'] !== '/';
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
