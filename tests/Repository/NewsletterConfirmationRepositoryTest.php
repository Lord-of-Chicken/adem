<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\NewsletterConfirmation;
use App\Repository\NewsletterConfirmationRepository;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests the GDPR purge of expired, never-confirmed newsletter tokens.
 *
 * Requires the test database. Skipped cleanly when the DB is unreachable —
 * see docs/TESTING.md.
 */
final class NewsletterConfirmationRepositoryTest extends KernelTestCase
{
    private function em(): EntityManagerInterface
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $registry->getManager();

        return $em;
    }

    private function repo(): NewsletterConfirmationRepository
    {
        /** @var NewsletterConfirmationRepository $repo */
        $repo = static::getContainer()->get(NewsletterConfirmationRepository::class);

        return $repo;
    }

    private function ensureDatabase(): void
    {
        try {
            $this->em()->getConnection()->executeQuery('SELECT 1');
        } catch (ConnectionException | DriverException | \Doctrine\DBAL\Exception $e) {
            self::markTestSkipped('Test database unavailable: '.$e->getMessage());
        }
    }

    /**
     * Only expired AND unconfirmed tokens are purged; confirmed tokens (consent
     * proof) and not-yet-expired tokens survive.
     */
    public function testDeleteExpiredUnconfirmedKeepsConfirmedAndFresh(): void
    {
        self::bootKernel();
        $this->ensureDatabase();

        $em = $this->em();
        // Isolate: start from an empty table.
        $em->createQuery('DELETE FROM '.NewsletterConfirmation::class.' nc')->execute();

        $pending = new NewsletterConfirmation('pending@example.test', bin2hex(random_bytes(16)));
        $confirmed = new NewsletterConfirmation('confirmed@example.test', bin2hex(random_bytes(16)));
        $confirmed->confirm();

        $em->persist($pending);
        $em->persist($confirmed);
        $em->flush();

        // Reference time 8 days ahead → both tokens are past their 7-day expiry,
        // but only the unconfirmed one must be deleted.
        $deleted = $this->repo()->deleteExpiredUnconfirmed(new \DateTimeImmutable('+8 days'));

        self::assertSame(1, $deleted, 'Exactly one expired unconfirmed token should be purged.');
        self::assertCount(
            1,
            $this->repo()->findAll(),
            'The confirmed token must survive as consent proof.'
        );
        self::assertNotNull(
            $this->repo()->findOneBy(['email' => 'confirmed@example.test']),
            'The confirmed token must be the one that remains.'
        );
    }

    /**
     * A token still within its validity window is never purged.
     */
    public function testDeleteExpiredUnconfirmedKeepsFreshToken(): void
    {
        self::bootKernel();
        $this->ensureDatabase();

        $em = $this->em();
        $em->createQuery('DELETE FROM '.NewsletterConfirmation::class.' nc')->execute();

        $fresh = new NewsletterConfirmation('fresh@example.test', bin2hex(random_bytes(16)));
        $em->persist($fresh);
        $em->flush();

        // Reference time = now: the token expires in 7 days, so nothing is purged.
        $deleted = $this->repo()->deleteExpiredUnconfirmed();

        self::assertSame(0, $deleted, 'A non-expired token must not be purged.');
    }
}
