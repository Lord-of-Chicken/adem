<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class MediaUploadService
{
    public function __construct(
        private ValidatorInterface $validator,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function store(UploadedFile $file): string
    {
        $errors = $this->validator->validate($file, new Image(
            maxSize: 5 * 1024 * 1024,
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            maxPixels: 40_000_000,
            detectCorrupted: true,
        ));
        if (count($errors) !== 0 || !$file->isValid()) {
            throw new \InvalidArgumentException('Invalid image upload.');
        }
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Invalid image type.'),
        };
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->projectDir.'/public/img/ruelle', $name);
        return 'img/ruelle/'.$name;
    }
}
