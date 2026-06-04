<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Tusk\Contracts\Events\EventDispatcherInterface;
use Tusk\Contracts\Attributes\Service;
use TuskPress\Domain\Post\Event\PostSavedEvent;
use TuskPress\Domain\Post\Post;
use TuskPress\Domain\Post\PostId;
use TuskPress\Domain\Post\PostMeta;
use TuskPress\Domain\Post\PostQuery;
use TuskPress\Domain\Post\PostRepository;
use TuskPress\Domain\Post\PostType;

/**
 * MySqlPostRepository — Refactored to use Doctrine ORM ("Tusk Way")
 */
#[Service]
class MySqlPostRepository implements PostRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    public function findById(PostId $id): ?Post
    {
        return $this->em->find(Post::class, $id->value);
    }

    public function findBySlug(string $slug, PostType $type = PostType::Post): ?Post
    {
        return $this->em->getRepository(Post::class)->findOneBy([
            'slug' => $slug,
            'typeValue' => $type->value,
            // 'status' != 'trash' -- Doctrine doesn't support != in findOneBy easily, we need query builder
        ]);
        // TODO: build proper query builder if needed
    }

    /**
     * @return Post[]
     */
    public function findAll(PostQuery $query): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('p')->from(Post::class, 'p');

        $qb->andWhere('p.typeValue = :type')
           ->setParameter('type', $query->type->value);

        if ($query->status !== null) {
            $qb->andWhere('p.statusValue = :status')
               ->setParameter('status', $query->status->value);
        } else {
            $qb->andWhere("p.statusValue != 'trash'");
        }

        if ($query->authorId !== null) {
            $qb->andWhere('p.authorIdValue = :author')
               ->setParameter('author', $query->authorId);
        }

        if ($query->search !== null && $query->search !== '') {
            $qb->andWhere('(p.title LIKE :search OR p.content LIKE :search)')
               ->setParameter('search', '%' . $query->search . '%');
        }

        if ($query->parentId !== null) {
            $qb->andWhere('p.parentIdValue = :parent')
               ->setParameter('parent', $query->parentId);
        }

        if (!empty($query->includeIds)) {
            $qb->andWhere('p.idValue IN (:ids)')
               ->setParameter('ids', $query->includeIds);
        }

        $allowedOrderBy = ['createdAt' => 'p.createdAt', 'updatedAt' => 'p.updatedAt', 'title' => 'p.title', 'idValue' => 'p.idValue'];
        $orderField = $allowedOrderBy[$query->orderBy] ?? 'p.createdAt';
        $orderDir = strtoupper($query->order) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($orderField, $orderDir);
        $qb->setMaxResults($query->perPage);
        $qb->setFirstResult($query->offset());

        return $qb->getQuery()->getResult();
    }

    public function count(PostQuery $query): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(p.idValue)')->from(Post::class, 'p');

        $qb->andWhere('p.typeValue = :type')
           ->setParameter('type', $query->type->value);

        if ($query->status !== null) {
            $qb->andWhere('p.statusValue = :status')
               ->setParameter('status', $query->status->value);
        } else {
            $qb->andWhere("p.statusValue != 'trash'");
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(Post $post): Post
    {
        $this->em->persist($post);
        $this->em->flush();
        
        $this->dispatcher->dispatch(new PostSavedEvent($post));
        
        return $post;
    }

    public function delete(PostId $id): void
    {
        $post = $this->findById($id);
        if ($post) {
            $this->em->remove($post);
            $this->em->flush();
        }
    }

    public function trash(PostId $id): void
    {
        $post = $this->findById($id);
        if ($post) {
            $post->trash();
            $this->em->flush();
        }
    }

    public function restore(PostId $id): void
    {
        $post = $this->findById($id);
        if ($post) {
            $post->sendToDraft();
            $this->em->flush();
        }
    }

    /**
     * @return PostMeta[]
     */
    public function findMeta(PostId $id): array
    {
        return []; // TBD: Map PostMeta
    }

    public function saveMeta(PostId $id, string $key, mixed $value): void
    {
        // TBD
    }

    public function deleteMeta(PostId $id, string $key): void
    {
        // TBD
    }
}
