<?php

namespace App\Entity;

use App\Repository\MediaItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaItemRepository::class)]
#[ORM\Table(name: 'media_item')]
class MediaItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Chemin logique AssetMapper, ex. img/ruelle/IMG_0189.jpeg */
    #[ORM\Column(name: 'asset_path', length: 512)]
    private string $assetPath;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssetPath(): string
    {
        return $this->assetPath;
    }

    public function setAssetPath(string $assetPath): static
    {
        $this->assetPath = $assetPath;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }
}
