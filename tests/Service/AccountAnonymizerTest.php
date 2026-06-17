<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\User;
use App\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the GDPR account anonymisation service. No database, no kernel.
 */
final class AccountAnonymizerTest extends TestCase
{
    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('jean.dupont@example.com');
        $user->setFirstName('Jean');
        $user->setLastName('Dupont');
        $user->setAddress('Rue des Fleurs 1, Uccle');
        $user->setNewsletter(true);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('hashed-password');
        $user->setResetToken('token');
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        return $user;
    }

    public function testAnonymizeScrubsPersonalDataButKeepsTheUser(): void
    {
        $user = $this->makeUser();
        $originalPassword = $user->getPassword();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new AccountAnonymizer($em))->anonymize($user);

        self::assertTrue($user->isAnonymized());
        self::assertNotNull($user->getAnonymizedAt());
        self::assertStringContainsString('@deleted.local', (string) $user->getEmail());
        self::assertSame('Supprimé', $user->getFirstName());
        self::assertSame('Supprimé', $user->getLastName());
        self::assertNull($user->getAddress());
        self::assertFalse($user->isNewsletter());
        self::assertSame(['ROLE_USER'], $user->getRoles()); // setRoles([]) => getRoles() still guarantees ROLE_USER
        self::assertNotSame($originalPassword, $user->getPassword());
        self::assertNull($user->getResetToken());
        self::assertNull($user->getResetTokenExpiresAt());
    }

    public function testAnonymizeScrubsDonorNameInOrderCartData(): void
    {
        $user = $this->makeUser();

        $order = new Order();
        $order->setStripeCheckoutSessionId('cs_test_123');
        $order->setTotalCents(1000);
        $order->setCartData([
            ['line_id' => 'a', 'tier_id' => 'vip', 'quantity' => 1, 'donor_name' => 'Jean Dupont', 'custom_price_cents' => null],
            ['line_id' => 'b', 'tier_id' => 'std', 'quantity' => 2, 'donor_name' => null, 'custom_price_cents' => null],
        ]);
        $user->addOrder($order);

        $em = $this->createStub(EntityManagerInterface::class);
        (new AccountAnonymizer($em))->anonymize($user);

        $cartData = $order->getCartData();
        $line0 = $cartData[0] ?? null;
        $line1 = $cartData[1] ?? null;
        self::assertIsArray($line0);
        self::assertIsArray($line1);
        self::assertNull($line0['donor_name']);
        self::assertNull($line1['donor_name']);
        // Non-PII order data must be preserved (fiscal retention).
        self::assertSame('cs_test_123', $order->getStripeCheckoutSessionId());
        self::assertSame(1000, $order->getTotalCents());
    }

    public function testAnonymizeIsIdempotent(): void
    {
        $user = $this->makeUser();

        $em = $this->createMock(EntityManagerInterface::class);
        // flush only on the first (effective) call.
        $em->expects(self::once())->method('flush');

        $service = new AccountAnonymizer($em);
        $service->anonymize($user);
        $firstAnonymizedAt = $user->getAnonymizedAt();

        $service->anonymize($user);
        self::assertSame($firstAnonymizedAt, $user->getAnonymizedAt());
    }
}
