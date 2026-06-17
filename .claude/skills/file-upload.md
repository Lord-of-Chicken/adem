# File Upload Skill

## Context

The media gallery is core UX: photos of the ruelle (before/after, blooming garden) are
uploaded via the EasyAdmin backoffice and stored as `MediaItem` entities.

## Stack

```bash
# Flysystem for storage abstraction (local dev, S3 prod)
composer require league/flysystem-bundle

# S3 adapter for production
composer require league/flysystem-aws-s3-v3

# Image resize/optimization
composer require liip/imagine-bundle
```

## Upload flow

```
Admin submits form (EasyAdmin / MediaItem form)
  → Controller receives UploadedFile
  → MediaUploadService
      → validate MIME + size
      → generate unique filename (Uuid::v7())
      → store via FileStorageInterface
      → persist MediaItem entity (Doctrine)
  → redirect
```

## Entity + constraints

```php
// src/Entity/MediaItem.php
#[ORM\Entity(repositoryClass: MediaItemRepository::class)]
class MediaItem {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $storagePath;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $sizeBytes;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;
}

final class MediaItemConstraints {
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    public const ALLOWED_MIMES  = ['image/jpeg', 'image/png', 'image/webp'];
}
```

## Storage service

```php
interface FileStorageInterface {
    public function store(UploadedFile $file, string $directory): string;
    public function delete(string $path): void;
    public function url(string $path): string;
}

// Flysystem implementation (src/Service/)
final class FlysystemFileStorage implements FileStorageInterface {
    public function __construct(
        private readonly FilesystemOperator $storage,
        private readonly string $publicBaseUrl,
    ) {}

    public function store(UploadedFile $file, string $directory): string {
        $filename = Uuid::v7()->toRfc4122() . '.' . $file->guessExtension();
        $path = $directory . '/' . $filename;
        $this->storage->write($path, $file->getContent());
        return $path;
    }
}
```

## Symfony Form integration

```php
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

$builder->add('image', FileType::class, [
    'label'      => 'Photo de la ruelle',
    'required'   => false,
    'mapped'     => false,
    'constraints' => [
        new Assert\File(
            maxSize: '10M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'Format accepté : JPEG, PNG, WebP',
        ),
    ],
]);
```

## Twig display

```twig
{% for item in mediaItems %}
    <img src="{{ storage_url(item.storagePath) }}"
         alt="{{ item.caption ?? 'Photo de la ruelle' }}"
         loading="lazy"
         class="w-full object-cover rounded">
{% endfor %}
```

## Config (Flysystem)

```yaml
# config/packages/flysystem.yaml
flysystem:
    storages:
        media.gallery.storage:
            adapter: local
            options:
                directory: '%kernel.project_dir%/var/storage/media'
        # Production S3 — swap adapter in .env.prod
```

## Security rules

- Validate MIME type **server-side** — never trust browser Content-Type
- Store files **outside webroot** (`var/storage/`, not `public/`)
- Serve via controller action — never expose direct file URL
- Sanitize filenames — always use UUID, never the original filename
- Virus scan in production (ClamAV or cloud service)

## Production

- S3 or compatible (Scaleway, OVH Object Storage)
- Generate signed URLs for private assets
- Compress/resize server-side before storage
