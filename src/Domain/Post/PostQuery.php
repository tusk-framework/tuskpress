<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

/**
 * PostQuery — Value Object que representa os parâmetros de busca de posts.
 * Substitui o WP_Query do WordPress de forma explícita e type-safe.
 */
readonly class PostQuery
{
    public function __construct(
        public PostType         $type          = PostType::Post,
        public ?PostStatus      $status        = PostStatus::Publish,
        public int              $perPage       = 10,
        public int              $page          = 1,
        public string           $orderBy       = 'post_date',
        public string           $order         = 'DESC',
        public ?int             $authorId      = null,
        public ?int             $categoryId    = null,
        public ?int             $tagId         = null,
        public ?string          $search        = null,
        public ?int             $parentId      = null,
        /** @var int[] */
        public array            $includeIds    = [],
        /** @var int[] */
        public array            $excludeIds    = [],
        public ?string          $dateFrom      = null,
        public ?string          $dateTo        = null,
        public bool             $withMeta      = false,
        public bool             $withTerms     = false,
    ) {}

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public static function forFrontend(int $perPage = 10, int $page = 1): self
    {
        return new self(
            type:    PostType::Post,
            status:  PostStatus::Publish,
            perPage: $perPage,
            page:    $page,
            orderBy: 'post_date',
            order:   'DESC',
        );
    }

    public static function forAdmin(?PostStatus $status = null, int $perPage = 20, int $page = 1): self
    {
        return new self(
            type:    PostType::Post,
            status:  $status,
            perPage: $perPage,
            page:    $page,
            orderBy: 'post_modified',
            order:   'DESC',
        );
    }
}
