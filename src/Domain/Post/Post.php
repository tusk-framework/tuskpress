<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

use Doctrine\ORM\Mapping as ORM;
use TuskPress\Domain\User\UserId;

/**
 * Post — Aggregate Root do domínio de conteúdo.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wp_posts')]
#[ORM\HasLifecycleCallbacks]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: 'ID', type: 'integer')]
    public int $idValue;

    public ?PostId $id = null; // Transient wrapper for DDD

    #[ORM\Column(name: 'post_author', type: 'integer')]
    public int $authorIdValue;
    
    public ?UserId $authorId = null;

    #[ORM\Column(name: 'post_date', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'post_date_gmt', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAtGmt;

    #[ORM\Column(name: 'post_content', type: 'text')]
    public string $content;

    #[ORM\Column(name: 'post_title', type: 'text')]
    public string $title;

    #[ORM\Column(name: 'post_excerpt', type: 'text')]
    public string $excerpt;

    #[ORM\Column(name: 'post_status', type: 'string', length: 20)]
    public string $statusValue;

    public PostStatus $status;

    #[ORM\Column(name: 'comment_status', type: 'string', length: 20)]
    public string $commentStatus;

    #[ORM\Column(name: 'ping_status', type: 'string', length: 20)]
    public string $pingStatus;

    #[ORM\Column(name: 'post_password', type: 'string', length: 255)]
    public string $password;

    #[ORM\Column(name: 'post_name', type: 'string', length: 200)]
    public string $slug;

    #[ORM\Column(name: 'to_ping', type: 'text')]
    public string $toPing = '';

    #[ORM\Column(name: 'pinged', type: 'text')]
    public string $pinged = '';

    #[ORM\Column(name: 'post_modified', type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'post_modified_gmt', type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAtGmt;

    #[ORM\Column(name: 'post_content_filtered', type: 'text')]
    public string $contentFiltered = '';

    #[ORM\Column(name: 'post_parent', type: 'integer')]
    public int $parentIdValue = 0;

    public ?PostId $parentId = null;

    #[ORM\Column(name: 'guid', type: 'string', length: 255)]
    public string $guid = '';

    #[ORM\Column(name: 'menu_order', type: 'integer')]
    public int $menuOrder = 0;

    #[ORM\Column(name: 'post_type', type: 'string', length: 20)]
    public string $typeValue;

    public PostType $type;

    #[ORM\Column(name: 'post_mime_type', type: 'string', length: 100)]
    public string $mimeType = '';

    #[ORM\Column(name: 'comment_count', type: 'integer')]
    public int $commentCount = 0;

    // Transient props
    public \DateTimeImmutable $publishedAt;
    public array $meta = [];
    public array $termIds = [];

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->id = new PostId($this->idValue);
        $this->authorId = new UserId($this->authorIdValue);
        $this->parentId = $this->parentIdValue ? new PostId($this->parentIdValue) : null;
        $this->status = PostStatus::tryFrom($this->statusValue) ?? PostStatus::Draft;
        $this->type = PostType::tryFrom($this->typeValue) ?? PostType::Post;
        $this->publishedAt = $this->createdAt; // legacy approx
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onPrePersist(): void
    {
        if ($this->id !== null && $this->id->value !== 0) {
            $this->idValue = $this->id->value;
        }
        $this->authorIdValue = $this->authorId ? $this->authorId->value : 1;
        $this->parentIdValue = $this->parentId ? $this->parentId->value : 0;
        $this->statusValue = $this->status->value;
        $this->typeValue = $this->type->value;
    }

    public function __construct(
        PostId $id = new PostId(0),
        string $title = '',
        string $content = '',
        string $excerpt = '',
        string $slug = '',
        UserId $authorId = new UserId(1),
        PostType $type = PostType::Post,
        PostStatus $status = PostStatus::Draft
    ) {
        $this->id = $id;
        $this->idValue = $id->value;
        $this->title = $title;
        $this->content = $content;
        $this->excerpt = $excerpt;
        $this->slug = $slug;
        $this->authorId = $authorId;
        $this->type = $type;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->createdAtGmt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedAtGmt = new \DateTimeImmutable();
        $this->publishedAt = $this->createdAt;
        $this->commentStatus = 'open';
        $this->pingStatus = 'open';
        $this->password = '';
    }

    // Mutations
    public function publish(): self
    {
        $this->status = PostStatus::Publish;
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedAtGmt = new \DateTimeImmutable();
        return $this;
    }

    public function sendToDraft(): self
    {
        $this->status = PostStatus::Draft;
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedAtGmt = new \DateTimeImmutable();
        return $this;
    }

    public function trash(): self
    {
        $this->status = PostStatus::Trash;
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedAtGmt = new \DateTimeImmutable();
        return $this;
    }

    // Queries
    public function isPublished(): bool { return $this->status === PostStatus::Publish; }
    public function isDraft(): bool { return $this->status === PostStatus::Draft; }
    public function isTrashed(): bool { return $this->status === PostStatus::Trash; }
    public function isPasswordProtected(): bool { return $this->password !== ''; }
    public function isPage(): bool { return $this->type === PostType::Page; }

    public function getMetaValue(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function permalink(string $base = ''): string
    {
        return rtrim($base, '/') . '/' . $this->slug . '/';
    }
}
