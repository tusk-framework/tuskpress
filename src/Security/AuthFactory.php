<?php

declare(strict_types=1);

namespace TuskPress\Security;

use Tusk\Contracts\Attributes\Factory;
use Tusk\Security\Authentication\AuthManager;
use Tusk\Security\Contract\GuardInterface;

class AuthFactory
{
    #[Factory(provides: GuardInterface::class, scope: 'request')]
    public function createGuard(SessionGuard $sessionGuard): GuardInterface
    {
        return $sessionGuard;
    }

    #[Factory(provides: AuthManager::class, scope: 'request')]
    public function createAuthManager(SessionGuard $sessionGuard): AuthManager
    {
        $manager = new AuthManager();
        $manager->addGuard('session', $sessionGuard, true);
        return $manager;
    }
}
