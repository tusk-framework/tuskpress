<?php

declare(strict_types=1);

namespace TuskPress\Domain\User;

interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function findByLogin(string $login): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return User[]
     */
    public function findAll(int $perPage = 20, int $page = 1, ?UserRole $role = null): array;

    public function count(?UserRole $role = null): int;

    public function save(User $user): User;

    public function delete(UserId $id): void;

    public function saveMeta(UserId $id, string $key, mixed $value): void;

    public function getMeta(UserId $id, string $key): mixed;
}
