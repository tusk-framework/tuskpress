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
}
