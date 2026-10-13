<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Tusk\Contracts\Attributes\Service;
use TuskPress\Domain\User\User;
use TuskPress\Domain\User\UserId;
use TuskPress\Domain\User\UserRepository;
use TuskPress\Domain\User\UserRole;
use Tusk\Security\Contract\UserProviderInterface;
use Tusk\Security\Contract\UserInterface;

/**
 * MySqlUserRepository — Doctrine implementation of the user repository.
 */
#[Service(scope: 'request')]
class MySqlUserRepository implements UserRepository, UserProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function loadByIdentifier(string|int $identifier): ?UserInterface
    {
        return $this->findByLogin((string) $identifier) ?? $this->findById(new UserId((int) $identifier));
    }

    public function findById(UserId $id): ?User
    {
        return $this->em->find(User::class, $id->value);
    }

    public function findByLogin(string $login): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['login' => $login]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * @return User[]
     */
    public function findAll(int $perPage = 20, int $page = 1, ?UserRole $role = null): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('u')->from(User::class, 'u');
        
        // For MVP, not filtering by Role properly via wp_usermeta since we aren't mapping usermeta yet in this MVP
        
        $qb->orderBy('u.registeredAt', 'DESC');
        $qb->setMaxResults($perPage);
        $qb->setFirstResult(($page - 1) * $perPage);

        return $qb->getQuery()->getResult();
    }

    public function count(?UserRole $role = null): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(u.idValue)')->from(User::class, 'u');
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(User $user): User
    {
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    public function delete(UserId $id): void
    {
        $user = $this->findById($id);
        if ($user) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }

    public function saveMeta(UserId $id, string $key, mixed $value): void
    {
        // TBD: Map Usermeta
    }

    public function getMeta(UserId $id, string $key): mixed
    {
        // TBD: Map Usermeta
        return null;
    }
}
