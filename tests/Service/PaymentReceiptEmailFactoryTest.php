<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Service\PaymentReceiptEmailFactory;
use PHPUnit\Framework\TestCase;

final class PaymentReceiptEmailFactoryTest extends TestCase
{
    public function testCreatesFullReceiptForSiteWithPaidAmountsAndBothQuantities(): void
    {
        $email = (new PaymentReceiptEmailFactory('info@ruelledadem.com'))->create($this->order(), $this->receipt());
        self::assertSame('info@ruelledadem.com', $email->getTo()[0]->getAddress());
        self::assertSame('info@ruelledadem.com', $email->getFrom()[0]->getAddress());
        self::assertSame('jean@example.test', $email->getReplyTo()[0]->getAddress());
        self::assertStringStartsWith('[TEST]', (string) $email->getSubject());
        $text = (string) $email->getTextBody();
        foreach (['12,34 EUR', 'quantité facturée : 1', 'prix unitaire : 12,34 EUR', 'quantité choisie : 5', 'nom VIP : Jean',
            'Rue des Fleurs', 'cs_fixture', 'pi_fixture', 'ch_fixture', '08/10/2026 15:00:00 CEST', 'https://pay.stripe.com/receipts/payment/fixture'] as $value) {
            self::assertStringContainsString($value, $text);
        }
        self::assertStringContainsString('Consulter le reçu officiel Stripe', (string) $email->getHtmlBody());
    }

    public function testEscapesHostileCustomerAndVipContentInHtmlAndRejectsReplyToInjection(): void
    {
        $receipt = $this->receipt();
        $receipt['customer_name'] = '<script>alert("payer")</script>';
        $receipt['customer_email'] = "attacker@example.test\r\nBcc: victim@example.test";
        $receipt['lines'] = [['title' => '<img src=x onerror=alert(1)>', 'quantity' => 1, 'unit_amount_cents' => 1234, 'total_cents' => 1234, 'donor_name' => '<b>VIP</b>']];
        $email = (new PaymentReceiptEmailFactory('info@ruelledadem.com'))->create($this->order(), $receipt);
        $html = (string) $email->getHtmlBody();
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;VIP&lt;/b&gt;', $html);
        self::assertSame([], $email->getReplyTo());
        self::assertSame([], $email->getBcc());
    }

    public function testInvalidPayerEmailIsNotAddedToReplyToAndLiveSubjectIsClear(): void
    {
        $receipt = $this->receipt();
        $receipt['customer_email'] = 'invalid email';
        $receipt['livemode'] = true;
        $email = (new PaymentReceiptEmailFactory('info@ruelledadem.com'))->create($this->order(), $receipt);
        self::assertSame([], $email->getReplyTo());
        self::assertStringNotContainsString('[TEST]', (string) $email->getSubject());
        self::assertStringContainsString('Mode Stripe : Réel', (string) $email->getTextBody());
    }

    public function testRejectsUntrustedReceiptLink(): void
    {
        $receipt = $this->receipt();
        $receipt['receipt_url'] = 'https://evil.test/receipt';
        $this->expectException(\UnexpectedValueException::class);
        (new PaymentReceiptEmailFactory('info@ruelledadem.com'))->create($this->order(), $receipt);
    }

    public function testRejectsTotalMismatch(): void
    {
        $receipt = $this->receipt();
        $receipt['amount_cents'] = 999;
        $this->expectException(\UnexpectedValueException::class);
        (new PaymentReceiptEmailFactory('info@ruelledadem.com'))->create($this->order(), $receipt);
    }

    private function order(): Order
    {
        return (new Order())->setTotalCents(1234)->setPaidAt(new \DateTimeImmutable('2026-10-08 13:00:00 UTC'))
            ->setCartData([['tier_id' => 'begonias_vip', 'quantity' => 5, 'donor_name' => 'Jean']]);
    }

    /** @return array<string, mixed> */
    private function receipt(): array
    {
        return [
            'receipt_url' => 'https://pay.stripe.com/receipts/payment/fixture', 'amount_cents' => 1234, 'currency' => 'eur',
            'charge_id' => 'ch_fixture', 'payment_intent_id' => 'pi_fixture', 'session_id' => 'cs_fixture',
            'customer_name' => 'Jean', 'customer_email' => 'jean@example.test', 'customer_address' => 'Rue des Fleurs 1, Uccle',
            'payment_method' => 'visa •••• 4242', 'livemode' => false,
            'lines' => [['title' => 'Jardinière VIP', 'quantity' => 1, 'unit_amount_cents' => 1234, 'total_cents' => 1234, 'donor_name' => 'Jean']],
        ];
    }
}
