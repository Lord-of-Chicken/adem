<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\MediaItem;
use App\Tests\Support\BackendWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BackendPermissionsTest extends BackendWebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function protectedPaths(): iterable
    {
        foreach (['/admin', '/fr/admin/users', '/admin/media-item/upload', '/fr/admin/carousel/reorder',
            '/fr/profil', '/fr/profil/supprimer', '/fr/profil/newsletter'] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('protectedPaths')]
    public function testAnonymousCannotReachProtectedHandlers(string $path): void
    {
        $this->client->request(str_contains($path, 'upload') || str_contains($path, 'reorder') || str_contains($path, 'supprimer') || str_contains($path, 'newsletter') ? 'POST' : 'GET', $path);
        self::assertResponseRedirects('/fr/connexion');
    }

    public function testOrdinaryUserCannotAccessAdminOrUpload(): void
    {
        $this->client->loginUser($this->user());
        $this->client->request('GET', '/fr/admin/users');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/media-item/upload');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminUploadRequiresCsrfAndAFile(): void
    {
        $this->client->loginUser($this->user(admin: true));
        $this->client->request('POST', '/admin/media-item/upload');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/media-item/upload', [], [], ['HTTP_X_CSRF_TOKEN' => $this->csrf('media_upload')]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testReorderRejectsWholeMalformedBatchWithoutPartialWrite(): void
    {
        $this->client->loginUser($this->user(admin: true));
        $media = (new MediaItem())->setTitle('Fixture')->setAssetPath('img/fixture.png')->setSortOrder(7)->setPublished(true);
        $this->em->persist($media);
        $this->em->flush();
        $this->client->request('POST', '/fr/admin/carousel/reorder', [], [], [
            'HTTP_X_CSRF_TOKEN' => $this->csrf('carousel_reorder'), 'CONTENT_TYPE' => 'application/json',
        ], json_encode(['orders' => [['id' => $media->getId(), 'sortOrder' => 0], ['id' => [], 'sortOrder' => 1]]], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        $media = $this->reload($media);
        self::assertSame(7, $media->getSortOrder());
        $this->client->request('POST', '/fr/admin/carousel/reorder', [], [], [
            'HTTP_X_CSRF_TOKEN' => $this->csrf('carousel_reorder'), 'CONTENT_TYPE' => 'application/json',
        ], json_encode(['orders' => [['id' => $media->getId(), 'sortOrder' => 2]]], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        $media = $this->reload($media);
        self::assertSame(2, $media->getSortOrder());
    }

    public function testMutatingCartHandlersRejectMissingCsrf(): void
    {
        $this->client->loginUser($this->user());
        foreach (['/fr/panier/ajouter', '/fr/panier/vider', '/fr/panier/ligne/a/supprimer', '/fr/panier/ligne/a/quantite'] as $path) {
            $this->client->request('POST', $path);
            self::assertResponseStatusCodeSame(403);
        }
    }
}
