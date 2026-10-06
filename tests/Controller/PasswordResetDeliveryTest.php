<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\BackendWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class PasswordResetDeliveryTest extends BackendWebTestCase
{
    /** @return iterable<array{bool}> */
    public static function accounts(): iterable { yield [true]; yield [false]; }

    #[DataProvider('accounts')]
    public function testPublicResultDoesNotRevealAccountWhenMailerFails(bool $exists): void
    {
        if ($exists) { $this->user(); }
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($exists ? self::once() : self::never())->method('send')->willThrowException(new TransportException('fixture failure'));
        static::getContainer()->set('mailer.mailer', $mailer);
        $limiter = static::getContainer()->get('limiter.password_reset');
        self::assertInstanceOf(RateLimiterFactory::class, $limiter);
        $limiter->create('192.0.2.44')->reset();
        $this->client->setServerParameter('REMOTE_ADDR', '192.0.2.44');
        $crawler = $this->client->request('GET', '/fr/reset-password');
        $this->client->submit($crawler->filter('form')->form(['reset_password_request_form[email]' => 'backend-audit@example.test']));
        self::assertResponseRedirects('/fr/connexion');
        $session = $this->session([]);
        self::assertArrayHasKey('success', $session->getFlashBag()->peekAll());
        self::assertArrayNotHasKey('warning', $session->getFlashBag()->peekAll());
    }
}
