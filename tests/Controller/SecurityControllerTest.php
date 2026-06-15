<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Functional tests for the password-reset flow.
 *
 * Requires the test database (mysql, schema `ruellenadem_test`, migrations applied).
 * If the database is unreachable the tests are skipped rather than failed so the
 * suite stays green on machines where Docker/MySQL is down. See docs/TESTING.md.
 */
final class SecurityControllerTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $registry->getManager();

        return $em;
    }

    /**
     * Skip cleanly when the test DB cannot be reached.
     */
    private function ensureDatabase(): void
    {
        try {
            $this->em()->getConnection()->executeQuery('SELECT 1');
        } catch (ConnectionException | DriverException | \Doctrine\DBAL\Exception $e) {
            self::markTestSkipped('Test database unavailable: '.$e->getMessage());
        }
    }

    /**
     * Phase 1 regression: a user holding a reset token but with a NULL
     * resetTokenExpiresAt must not trigger a 500. The controller guards with
     * `!$user->getResetTokenExpiresAt()` and should redirect to the request page.
     */
    public function testResetWithNullExpiryDoesNotCrashAndRedirects(): void
    {
        $client = static::createClient();
        $this->ensureDatabase();

        $em = $this->em();

        // Raw token the visitor would carry in the URL; controller stores its sha256.
        $rawToken = 'phase1-null-expiry-token';
        $email = 'reset-null-expiry-'.uniqid('', true).'@example.test';

        $user = (new User())
            ->setEmail($email)
            ->setPassword('$2y$13$placeholderplaceholderplaceholderplaceholderpla')
            ->setRoles(['ROLE_USER'])
            ->setResetToken(hash('sha256', $rawToken));
        // Intentionally leave resetTokenExpiresAt as null (the bug condition).
        $user->setResetTokenExpiresAt(null);

        $em->persist($user);
        $em->flush();

        try {
            $client->request('GET', $this->resetUrl($rawToken));

            self::assertResponseStatusCodeSame(302, 'Null expiry must redirect, not 500.');
            // Redirected back to the forgot-password request page.
            self::assertResponseRedirects();
            $location = $client->getResponse()->headers->get('Location') ?? '';
            self::assertStringContainsString('reset-password', $location);
        } finally {
            $em->remove($user);
            $em->flush();
        }
    }

    private function resetUrl(string $token): string
    {
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');

        return $router->generate('app_reset_password', ['token' => $token]);
    }
}
