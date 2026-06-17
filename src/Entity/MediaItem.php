<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MediaItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a media item (image/video) in the gallery.
 */
#[ORM\Entity(repositoryClass: MediaItemRepository::class)]
#[ORM\Table(name: 'media_item')]
#[ORM\Index(fields: ['published', 'sortOrder'], name: 'idx_media_item_published_sort')]
class MediaItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Chemin logique AssetMapper, ex. img/ruelle/IMG_0189.jpeg */
    #[ORM\Column(name: 'asset_path', length: 512, nullable: true)]
    private ?string $assetPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    /**
     * Gets the ID of the media item.
     *
     * @return int|null The ID or null if not persisted
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the asset path.
     *
     * @return string|null The asset path
     */
    public function getAssetPath(): ?string
    {
        return $this->assetPath;
    }

    /**
     * Sets the asset path.
     *
     * @param string|null $assetPath The asset path
     * @return static The entity for method chaining
     */
    public function setAssetPath(?string $assetPath): static
    {
        $this->assetPath = $assetPath;

        return $this;
    }

    /**
     * Gets the title.
     *
     * @return string|null The title
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Sets the title.
     *
     * @param string|null $title The title
     * @return static The entity for method chaining
     */
    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets the alt text for accessibility.
     *
     * @return string|null The alt text
     */
    public function getAlt(): ?string
    {
        return $this->alt;
    }

    /**
     * Sets the alt text for accessibility.
     *
     * @param string|null $alt The alt text
     * @return static The entity for method chaining
     */
    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    /**
     * Gets the sort order.
     *
     * @return int The sort order
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Sets the sort order.
     *
     * @param int $sortOrder The sort order
     * @return static The entity for method chaining
     */
    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * Checks if the media item is published.
     *
     * @return bool True if published
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * Sets the published status.
     *
     * @param bool $published Whether the item is published
     * @return static The entity for method chaining
     */
    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }
}
