<?php

declare(strict_types=1);

namespace TuskPress\Tests\Domain\User;

use PHPUnit\Framework\TestCase;
use TuskPress\Domain\User\User;
use TuskPress\Domain\User\UserId;
use TuskPress\Domain\User\UserRole;

final class UserTest extends TestCase
{
    public function testCreateGeneratesUrlFriendlyNicenameFromLogin(): void
    {
        $user = User::create(
            id: new UserId(2),
            login: 'Smoke_User 42',
            email: 'smoke@example.com',
            displayName: 'Smoke User',
            role: UserRole::Subscriber,
            plainPassword: 'secret',
        );

        self::assertSame('smoke-user-42', $user->nicename);
    }

    public function testCreateGeneratesNicenameFromAsciiPartsOfInvalidUtf8Login(): void
    {
        $user = User::create(
            id: new UserId(2),
            login: "\xFFAdmin_User",
            email: 'smoke@example.com',
            displayName: 'Smoke User',
            role: UserRole::Subscriber,
            plainPassword: 'secret',
        );

        self::assertSame('admin-user', $user->nicename);
    }

    public function testCreatePreservesValidUnicodeLettersInNicename(): void
    {
        $user = User::create(
            id: new UserId(2),
            login: 'Élodie_42',
            email: 'smoke@example.com',
            displayName: 'Élodie',
            role: UserRole::Subscriber,
            plainPassword: 'secret',
        );

        self::assertSame('élodie-42', $user->nicename);
    }
}
