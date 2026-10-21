<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\BackendWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ContactCartAndRoutesTest extends BackendWebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function publicPaths(): iterable
    {
        foreach (['/fr/', '/en/', '/nl/', '/fr/galerie', '/fr/presse', '/fr/faq', '/sitemap.xml', '/health', '/fr/panier'] as $path) { yield $path => [$path]; }
    }

    #[DataProvider('publicPaths')]
    public function testPublicBackendRoutesRespondWithoutAuthentication(string $path): void
    {
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('nosniff', $this->client->getResponse()->headers->get('X-Content-Type-Options'));
        if ($path === '/health') {
            self::assertSame(['status' => 'ok'], json_decode($this->client->getResponse()->getContent() ?: '', true));
            self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function testLocaleRedirectCannotLeaveCurrentHost(): void
    {
        $this->client->request('GET', '/fr/locale/en', [], [], ['HTTP_REFERER' => 'https://attacker.example/path']);
        self::assertResponseRedirects('/en/');
        $this->client->request('GET', '/fr/locale/nl', [], [], ['HTTP_REFERER' => 'http://localhost/fr/galerie?filter=one']);
        self::assertResponseRedirects('/nl/galerie?filter=one');
        $this->client->request('GET', '/galerie');
        self::assertResponseRedirects('/fr/galerie', 301);
    }

    public function testCartAddConsentValidationAndQuantityFlow(): void
    {
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $csrf = $this->csrf('cart_add');
        $this->client->request('POST', '/fr/panier/ajouter', ['_token' => $csrf, 'tier_id' => 'vip_20', 'quantity' => 5, 'donor_name' => 'Private', 'donor_consent' => 0], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(200);
        $session = $this->session([]);
        $lines = $session->get('participation_cart_v1');
        self::assertIsArray($lines);
        self::assertIsArray($lines[0]);
        self::assertNull($lines[0]['donor_name']);
        self::assertIsString($lines[0]['line_id']);
        $id = $lines[0]['line_id'];
        $this->client->request('POST', '/fr/panier/ligne/'.$id.'/quantite', ['_token' => $this->csrf('cart_quantity'), 'quantity' => 8], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($data);
        self::assertSame('20,00', $data['newLineTotal']);
        self::assertSame('20,00', $data['newTotalHtml']);
        $this->client->request('POST', '/fr/panier/ajouter', ['_token' => $this->csrf('cart_add'), 'tier_id' => 'free_donation', 'amount' => '0.99'], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $data = json_decode($this->client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($data);
        self::assertFalse($data['success']);
        $this->client->request('POST', '/fr/panier/ligne/'.$id.'/supprimer', ['_token' => $this->csrf('cart_remove')]);
        self::assertResponseRedirects('/fr/panier');
        self::assertSame([], $this->session([])->get('participation_cart_v1'));
    }

    public function testContactValidatesEscapesAndRateLimits(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (RawMessage $message) use (&$sent): void {
            $sent = $message;
        });
        static::getContainer()->set('mailer.mailer', $mailer);
        $limiter = static::getContainer()->get('limiter.contact_form');
        self::assertInstanceOf(RateLimiterFactory::class, $limiter);
        $limiter->create('192.0.2.50')->reset();
        $this->client->setServerParameter('REMOTE_ADDR', '192.0.2.50');
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $crawler = $this->client->request('GET', '/fr/contact');
        $this->client->submit($crawler->filter('form')->form([
            'contact_form[name]' => 'Fixture', 'contact_form[email]' => 'bad', 'contact_form[message]' => 'short',
        ]));
        self::assertResponseStatusCodeSame(200);
        $crawler = $this->client->request('GET', '/fr/contact');
        $valid = ['contact_form[name]' => 'Fixture', 'contact_form[email]' => 'person@example.test', 'contact_form[message]' => '<script>Never execute this message.</script>'];
        $this->client->submit($crawler->filter('form')->form($valid));
        self::assertResponseRedirects('/fr/contact');
        self::assertInstanceOf(Email::class, $sent);
        self::assertSame('person@example.test', $sent->getReplyTo()[0]->getAddress());
        $html = $sent->getHtmlBody();
        self::assertIsString($html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        $limiter = static::getContainer()->get('limiter.contact_form');
        self::assertInstanceOf(RateLimiterFactory::class, $limiter);
        $limiter->create('192.0.2.50')->consume(4);
        $crawler = $this->client->request('GET', '/fr/contact');
        $this->client->submit($crawler->filter('form')->form($valid));
        self::assertResponseRedirects('/fr/contact');
        self::assertArrayHasKey('error', $this->session([])->getFlashBag()->peekAll());
    }
}
