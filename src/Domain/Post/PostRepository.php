<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

/**
 * PostRepository — Interface do repositório de posts.
 * Define o contrato de persistência independente de implementação.
 */
interface PostRepository
{
    public function findById(PostId $id): ?Post;

    public function findBySlug(string $slug, PostType $type = PostType::Post): ?Post;

    /**
     * @return Post[]
     */
    public function findAll(PostQuery $query): array;

    public function count(PostQuery $query): int;

    public function save(Post $post): Post;

    public function delete(PostId $id): void;

    public function trash(PostId $id): void;

    public function restore(PostId $id): void;

    /**
     * @return PostMeta[]
     */
    public function findMeta(PostId $id): array;

    public function saveMeta(PostId $id, string $key, mixed $value): void;

    public function deleteMeta(PostId $id, string $key): void;
}
