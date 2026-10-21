<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateAdminUserCommand;
use App\Command\FixMediaItemPathsCommand;
use App\Command\PurgeNewsletterTokensCommand;
use App\Command\RestoreMediaItemPathsCommand;
use App\Command\SeedParticipationTiersCommand;
use App\Entity\MediaItem;
use App\Entity\NewsletterConfirmation;
use App\Entity\ParticipationTier;
use App\Entity\User;
use App\Tests\Support\BackendWebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BackendCommandsTest extends BackendWebTestCase
{
    public function testCreateAdminUpsertsAndHashesPassword(): void
    {
        $tester = new CommandTester(self::service(CreateAdminUserCommand::class));
        self::assertSame(0, $tester->execute(['email' => 'admin-command@example.test', 'password' => 'Fixture-password-123']));
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin-command@example.test']);
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, 'Fixture-password-123'));
        self::assertSame(0, $tester->execute(['email' => 'admin-command@example.test', 'password' => 'Other-password-123', '--first-name' => 'Changed']));
        $this->em->refresh($user);
        self::assertSame('Changed', $user->getFirstName());
        self::assertSame(1, $this->em->getRepository(User::class)->count(['email' => 'admin-command@example.test']));
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, 'Other-password-123'));
    }

    public function testTierSeedIsIdempotentAndPreservesAdminEditUnlessForced(): void
    {
        $repo = $this->em->getRepository(ParticipationTier::class);
        $tier = $repo->find('begonia_unit');
        self::assertNotNull($tier);
        $tier->setUnitPriceEur('2.00');
        $this->em->flush();
        $tester = new CommandTester(self::service(SeedParticipationTiersCommand::class));
        self::assertSame(0, $tester->execute([]));
        $this->em->refresh($tier);
        self::assertSame('2.00', $tier->getUnitPriceEur());
        self::assertSame(5, $repo->count([]));
        self::assertSame(0, $tester->execute(['--force' => true]));
        $this->em->refresh($tier);
        self::assertSame('1.00', $tier->getUnitPriceEur());
        self::assertSame(5, $repo->count([]));
    }

    public function testPathCommandsAreRepeatable(): void
    {
        $media = (new MediaItem())->setTitle('Fixture')->setAssetPath('img/ruelle/fixture.png');
        $this->em->persist($media);
        $this->em->flush();
        $fix = new CommandTester(self::service(FixMediaItemPathsCommand::class));
        $fix->execute([]);
        $fix->assertCommandIsSuccessful();
        $fix->execute([]);
        $fix->assertCommandIsSuccessful();
        $this->em->refresh($media);
        self::assertSame('fixture.png', $media->getAssetPath());
        $restore = new CommandTester(self::service(RestoreMediaItemPathsCommand::class));
        $restore->execute([]);
        $restore->assertCommandIsSuccessful();
        $restore->execute([]);
        $restore->assertCommandIsSuccessful();
        $this->em->refresh($media);
        self::assertSame('img/ruelle/fixture.png', $media->getAssetPath());
    }

    public function testTokenPurgeCommandPreservesConfirmedConsent(): void
    {
        $confirmation = new NewsletterConfirmation('command@example.test', 'command-token');
        $confirmation->confirm();
        $this->em->persist($confirmation);
        $this->em->flush();
        $tester = new CommandTester(self::service(PurgeNewsletterTokensCommand::class));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('expired unconfirmed', $tester->getDisplay());
        self::assertNotNull($this->em->getRepository(NewsletterConfirmation::class)->findOneByToken('command-token'));
    }
    public function testAdminCreationRejectsInvalidInputAndAnonymizedAccount(): void
    {
        $tester = new CommandTester(self::service(CreateAdminUserCommand::class));
        self::assertSame(2, $tester->execute(['email' => 'invalid', 'password' => 'short'], ['interactive' => false]));
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
        $user = $this->user('erased@example.test')->setAnonymizedAt(new \DateTimeImmutable());
        $this->em->flush();
        self::assertSame(2, $tester->execute(['email' => 'erased@example.test', 'password' => 'Fixture-password-123']));
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGallerySeederIgnoresMissingFolderAndDoesNotDuplicateFiles(): void
    {
        $root = sys_get_temp_dir().'/adem-seed-'.bin2hex(random_bytes(8));
        $seeder = new \App\Service\RuelleDatabaseSeeder($root);
        $repo = $this->em->getRepository(MediaItem::class);
        $seeder->seed($this->em);
        self::assertSame(0, $repo->count([]));
        mkdir($root.'/assets/img/ruelle', 0700, true);
        try {
            file_put_contents($root.'/assets/img/ruelle/b.jpg', 'seed fixture');
            file_put_contents($root.'/assets/img/ruelle/a.png', 'seed fixture');
            file_put_contents($root.'/assets/img/ruelle/ignored.txt', 'ignored');
            $tester = new CommandTester(new \App\Command\SeedRuelleCommand($this->em, $seeder));
            self::assertSame(0, $tester->execute([]));
            $tester->execute([]);
            $tester->assertCommandIsSuccessful();
            self::assertSame(2, $repo->count([]));
            $media = $repo->findOneBy(['assetPath' => 'img/ruelle/a.png']);
            self::assertNotNull($media);
            self::assertSame(0, $media->getSortOrder());
            self::assertTrue($media->isPublished());
            self::assertSame('La Ruelle d’Adem', $media->getAlt());
        } finally {
            foreach (glob($root.'/assets/img/ruelle/*') ?: [] as $file) { unlink($file); }
            rmdir($root.'/assets/img/ruelle'); rmdir($root.'/assets/img'); rmdir($root.'/assets'); rmdir($root);
        }
    }

}
