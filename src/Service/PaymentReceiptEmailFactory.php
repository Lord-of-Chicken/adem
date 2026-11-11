<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

/** Composes the merchant notification; transport and retries belong to the sender. */
final readonly class PaymentReceiptEmailFactory
{
    public function __construct(
        #[Autowire(param: 'app.contact_email')]
        private string $siteEmail,
    ) {
    }

    /** @param array<string, mixed> $receipt */
    public function create(Order $order, array $receipt): Email
    {
        $url = $receipt['receipt_url'] ?? null;
        $amount = $receipt['amount_cents'] ?? null;
        if (!StripeReceiptProvider::isReceiptUrl($url) || !is_string($url) || !is_int($amount)
            || $amount !== $order->getTotalCents() || ($receipt['currency'] ?? null) !== 'eur'
            || !is_bool($receipt['livemode'] ?? null) || !is_array($receipt['lines'] ?? null)) {
            throw new \UnexpectedValueException('Cannot compose an incomplete or inconsistent payment receipt.');
        }
        $orderId = $order->getId();
        $date = $order->getPaidAt() ?? $order->getCreatedAt();
        $details = [
            'Commande' => $orderId === null ? 'Non renseignée' : '#'.$orderId,
            'Date du paiement' => $date?->setTimezone(new \DateTimeZone('Europe/Brussels'))->format('d/m/Y H:i:s T') ?? 'Non renseignée',
            'Mode Stripe' => $receipt['livemode'] ? 'Réel' : 'Test',
            'Montant payé' => self::money($amount),
            'Nom du payeur' => self::string($receipt['customer_name'] ?? null),
            'Email du payeur' => self::string($receipt['customer_email'] ?? null),
            'Adresse de facturation' => self::string($receipt['customer_address'] ?? null),
            'Moyen de paiement' => self::string($receipt['payment_method'] ?? null),
            'Session Stripe' => self::string($receipt['session_id'] ?? null),
            'Paiement Stripe' => self::string($receipt['payment_intent_id'] ?? null),
            'Transaction Stripe' => self::string($receipt['charge_id'] ?? null),
        ];
        $text = "Paiement confirmé — Ruelle d’Adem\n\n";
        $html = '<h1>Paiement confirmé — Ruelle d’Adem</h1><dl>';
        foreach ($details as $label => $value) {
            $text .= $label.' : '.$value."\n";
            $html .= '<dt>'.self::escape($label).'</dt><dd>'.self::escape($value).'</dd>';
        }
        $html .= '</dl><h2>Participations payées</h2><ul>';
        $text .= "\nParticipations payées :\n";
        foreach ($receipt['lines'] as $line) {
            if (!is_array($line) || !is_string($line['title'] ?? null) || !is_int($line['quantity'] ?? null)
                || !is_int($line['total_cents'] ?? null)) {
                throw new \UnexpectedValueException('Cannot compose an invalid payment line.');
            }
            $description = $line['title'].' — quantité facturée : '.$line['quantity'];
            if (is_int($line['unit_amount_cents'] ?? null)) {
                $description .= ' — prix unitaire : '.self::money($line['unit_amount_cents']);
            }
            $description .= ' — total : '.self::money($line['total_cents']);
            if (is_string($line['donor_name'] ?? null) && $line['donor_name'] !== '') {
                $description .= ' — nom VIP : '.$line['donor_name'];
            }
            $text .= '- '.$description."\n";
            $html .= '<li>'.self::escape($description).'</li>';
        }
        $html .= '</ul>';
        // A flat-priced tier has one billed Stripe item but may represent several
        // physical flowers. Preserve the original order quantities separately.
        $cartDescriptions = [];
        foreach ($order->getCartData() as $line) {
            if (is_array($line) && is_string($line['tier_id'] ?? null) && is_int($line['quantity'] ?? null)) {
                $description = $line['tier_id'].' — quantité choisie : '.$line['quantity'];
                if (is_string($line['donor_name'] ?? null) && $line['donor_name'] !== '') {
                    $description .= ' — nom VIP : '.$line['donor_name'];
                }
                $cartDescriptions[] = $description;
            }
        }
        if ($cartDescriptions !== []) {
            $text .= "\nDétail de la commande :\n";
            $html .= '<h2>Détail de la commande</h2><ul>';
            foreach ($cartDescriptions as $description) {
                $text .= '- '.$description."\n";
                $html .= '<li>'.self::escape($description).'</li>';
            }
            $html .= '</ul>';
        }
        $text .= "\nReçu officiel Stripe : ".$url."\n";
        $html .= '<p><a href="'.self::escape($url).'">Consulter le reçu officiel Stripe</a></p>';
        $email = (new Email())->from($this->siteEmail)->to($this->siteEmail)
            ->subject(($receipt['livemode'] ? '' : '[TEST] ').'Paiement reçu — commande '.($orderId ?? 'Stripe').' — '.self::money($amount))
            ->text($text)->html($html);
        $payerEmail = $receipt['customer_email'] ?? null;
        if (is_string($payerEmail) && filter_var($payerEmail, FILTER_VALIDATE_EMAIL) !== false
            && !str_contains($payerEmail, "\r") && !str_contains($payerEmail, "\n")) {
            $email->replyTo($payerEmail);
        }
        return $email;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : 'Non renseigné';
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' EUR';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
