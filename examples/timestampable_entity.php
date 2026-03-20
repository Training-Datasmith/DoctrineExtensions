<?php

declare(strict_types=1);

/**
 * Example: Use Timestampable and Sluggable behaviors on a Doctrine entity.
 *
 * In a Symfony application the listeners are registered as services automatically
 * by the StofDoctrineExtensionsBundle. This example shows the entity definition.
 */

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

/**
 * An article entity with automatic timestamps, a generated slug,
 * and soft-delete support.
 */
#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class Article
{
    // Provides $createdAt and $updatedAt with automatic Timestampable listeners.
    use TimestampableEntity;

    // Provides $deletedAt with automatic SoftDeleteable listeners.
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    /**
     * Auto-generated URL slug derived from $title.
     * Updated automatically when $title changes.
     */
    #[Gedmo\Slug(fields: ['title'])]
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): string { return $this->slug; }
}
