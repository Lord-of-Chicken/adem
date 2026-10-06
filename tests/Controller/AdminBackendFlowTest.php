<?php

declare(strict_types=1);
namespace App\Tests\Controller;

use App\Entity\MediaItem;
use App\Tests\Support\BackendWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminBackendFlowTest extends BackendWebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function paths(): iterable
    {
        foreach (['/fr/admin', '/fr/admin/carousel', '/fr/admin/users?newsletter=subscribed&page=999', '/admin/media-item', '/admin/user', '/admin/order', '/admin/participation-tier', '/admin/translations/fr'] as $path) { yield $path => [$path]; }
    }
    #[DataProvider('paths')]
    public function testAdminCanReadBackendLists(string $path): void
    {
        $this->client->loginUser($this->user(admin: true));
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(200);
    }
    public function testCarouselCreateEditDeletePersistsOnlyAuthorizedChanges(): void
    {
        $this->client->loginUser($this->user(admin: true));
        $this->client->request('POST', '/fr/admin/carousel/add', ['_token' => $this->csrf('carousel_manage'), 'title' => 'Audit media', 'asset_path' => 'img/fixture.png']);
        self::assertResponseRedirects('/fr/admin/carousel');
        $media = $this->em->getRepository(MediaItem::class)->findOneBy(['title' => 'Audit media']);
        self::assertNotNull($media);
        $id = $media->getId();
        $this->client->request('POST', '/fr/admin/carousel/edit/'.$id, ['_token' => $this->csrf('carousel_manage'), 'title' => 'Edited', 'asset_path' => 'img/edited.png']);
        self::assertResponseRedirects('/fr/admin/carousel');
        $media = $this->reload($media);
        self::assertSame('Edited', $media->getTitle());
        self::assertSame('img/edited.png', $media->getAssetPath());
        $this->client->request('POST', '/fr/admin/carousel/delete/'.$id);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em->find(MediaItem::class, $id));
        $this->client->request('POST', '/fr/admin/carousel/delete/'.$id, ['_token' => $this->csrf('carousel_delete_'.$id)]);
        self::assertResponseRedirects('/fr/admin/carousel');
        self::assertNull($this->em->find(MediaItem::class, $id));
    }
}
