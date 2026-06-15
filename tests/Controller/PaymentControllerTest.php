<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Functional tests for the Stripe payment return endpoints.
 *
 * Requires the test database (the success route looks up an Order by Stripe
 * session id). Skipped cleanly when the DB is unreachable — see docs/TESTING.md.
 */
final class PaymentControllerTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $registry->getManager();

        return $em;
    }

    private function ensureDatabase(): void
    {
        try {
            $this->em()->getConnection()->executeQuery('SELECT 1');
        } catch (ConnectionException | DriverException | \Doctrine\DBAL\Exception $e) {
            self::markTestSkipped('Test database unavailable: '.$e->getMessage());
        }
    }

    private function newSession(): SessionInterface
    {
        /** @var SessionFactoryInterface $factory */
        $factory = static::getContainer()->get('session.factory');

        return $factory->createSession();
    }

    /**
     * Anti-IDOR: hitting /payment/success with a session_id that maps to no
     * Order must NOT clear the visitor's cart and must NOT 500 — it simply
     * redirects back to the cart, leaving the cart intact.
     */
    public function testSuccessWithUnknownSessionDoesNotClearCart(): void
    {
        $client = static::createClient();
        $this->ensureDatabase();

        // Build a standalone session, seed a cart into it (mirroring CartService's
        // storage key/shape), persist it, and hand its cookie to the client so the
        // controller request runs against a real, populated cart.
        $session = $this->newSession();
        $session->set('participation_cart_v1', [[
            'line_id' => bin2hex(random_bytes(8)),
            'tier_id' => 'begonia_unit',
            'quantity' => 2,
            'donor_name' => null,
            'custom_price_cents' => null,
        ]]);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $client->request('GET', $this->successUrl('cs_test_nonexistent_'.uniqid('', true)));

        self::assertResponseStatusCodeSame(302, 'Unknown session must redirect, not 500.');
        self::assertResponseRedirects($this->url('app_cart_index'), null, 'Should redirect to the cart.');

        // The cart must still be populated: no Order matched, so clear() was skipped.
        // Reload the persisted session by id and confirm the cart bag survived.
        $reloaded = $this->newSession();
        $reloaded->setId($session->getId());
        $reloaded->start();
        self::assertNotEmpty(
            $reloaded->get('participation_cart_v1', []),
            'Cart must NOT be cleared for an unknown session (anti-IDOR).'
        );
    }

    private function successUrl(string $sessionId): string
    {
        return $this->url('app_payment_success', ['session_id' => $sessionId]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function url(string $route, array $params = []): string
    {
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');

        return $router->generate($route, $params);
    }
}
