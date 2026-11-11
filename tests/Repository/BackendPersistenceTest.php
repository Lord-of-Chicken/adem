<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\MediaItem;
use App\Entity\Order;
use App\Repository\MediaItemRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Tests\Support\BackendWebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class BackendPersistenceTest extends BackendWebTestCase
{
    public function testGalleryCacheInvalidatesAfterDirectOrmWrite(): void
    {
        $configuration = $this->em->getConfiguration();
        $previous = $configuration->getResultCache();
        $configuration->setResultCache(new ArrayAdapter());
        try {
            $media = (new MediaItem())->setTitle('Fixture')->setAssetPath('img/fixture.png')->setSortOrder(1)->setPublished(true);
            $this->em->persist($media);
            $this->em->flush();
            $repo = self::service(MediaItemRepository::class);
            self::assertCount(1, $repo->findPublishedOrdered());
            $media->setPublished(false);
            $this->em->flush();
            self::assertSame([], $repo->findPublishedOrdered());
            $media->setPublished(true);
            $repo->save($media, true);
            self::assertCount(1, $repo->findPublishedOrdered());
            $repo->remove($media, true);
            self::assertSame([], $repo->findPublishedOrdered());
        } finally { $configuration->setResultCache($previous ?? new ArrayAdapter()); }
    }

    public function testUserPaginationFilteringAndPasswordUpgrade(): void
    {
        $first = $this->user('one@example.test');
        $second = $this->user('two@example.test')->setNewsletter(true);
        $third = $this->user('three@example.test');
        $this->em->flush();
        $repo = self::service(UserRepository::class);
        self::assertSame(3, $repo->countUsers());
        self::assertSame(1, $repo->countUsers(true));
        self::assertSame(2, $repo->countUsers(false));
        self::assertSame([$first], $repo->findPaginated(0, 1));
        self::assertSame([$second], $repo->findPaginated(1, 1, true));
        self::assertSame([$third], $repo->findPaginated(2, 1, false));
        $repo->upgradePassword($first, 'new-fixture-hash');
        $this->em->refresh($first);
        self::assertSame('new-fixture-hash', $first->getPassword());
    }

    public function testPendingOrderLookupIsScopedToOwnerAndStatus(): void
    {
        $first = $this->user('first@example.test');
        $second = $this->user('second@example.test');
        $pending = (new Order())->setUser($first)->setTotalCents(100)->setStripeCheckoutSessionId('cs_pending')->setStripePaymentIntentId('pi_pending');
        $paid = (new Order())->setUser($first)->setTotalCents(100)->setStripeCheckoutSessionId('cs_paid')->markAsPaid();
        $repo = self::service(OrderRepository::class);
        $repo->save($pending);
        $repo->save($paid, true);
        self::assertSame($pending, $repo->findUnpaidOrderByUser($first));
        self::assertNull($repo->findUnpaidOrderByUser($second));
        self::assertSame($pending, $repo->findByStripePaymentIntentId('pi_pending'));
        self::assertSame($paid, $repo->findByStripeCheckoutSessionId('cs_paid'));
    }
}
