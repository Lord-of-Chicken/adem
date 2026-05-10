# File Upload Skill

## Context

Animal photos are core UX. Every signalement must support multiple photos.

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
User submits form
  → Controller receives UploadedFile[]
  → UploadAnimalPhotosHandler (Application layer)
      → validate MIME + size (domain rules)
      → generate unique filename (Uuid::v7())
      → store via FileStorageInterface (port)
      → persist Photo entity (Doctrine)
  → redirect
```

## Domain model

```php
// src/AnimalReport/Domain/ValueObject/AnimalPhoto.php
readonly class AnimalPhoto {
    public function __construct(
        public Uuid $id,
        public string $storagePath,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}

final class AnimalPhotoConstraints {
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    public const ALLOWED_MIMES  = ['image/jpeg', 'image/png', 'image/webp'];
    public const MAX_PER_REPORT = 5;
}
```

## Storage port (Infrastructure)

```php
// Domain port
interface FileStorageInterface {
    public function store(UploadedFile $file, string $directory): string;
    public function delete(string $path): void;
    public function url(string $path): string;
}

// Flysystem adapter
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

$builder->add('photos', FileType::class, [
    'label'      => 'Photos de l\'animal',
    'multiple'   => true,
    'required'   => false,
    'mapped'     => false,
    'constraints' => [
        new Assert\All([
            new Assert\File(
                maxSize: '10M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                mimeTypesMessage: 'Format accepté : JPEG, PNG, WebP',
            ),
        ]),
        new Assert\Count(max: 5),
    ],
]);
```

## Twig display

```twig
{% for photo in report.photos %}
    <img src="{{ storage_url(photo.storagePath) }}"
         alt="Photo de {{ report.animalName }}"
         loading="lazy"
         class="w-full object-cover rounded">
{% endfor %}
```

## Config (Flysystem)

```yaml
# config/packages/flysystem.yaml
flysystem:
    storages:
        animal.photos.storage:
            adapter: local
            options:
                directory: '%kernel.project_dir%/var/storage/photos'
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
- Generate signed URLs for private photos
- Compress/resize server-side before storage
