<?php

declare(strict_types=1);

namespace TuskPress\Security;

use Tusk\Security\Contract\GuardInterface;
use Tusk\Security\Contract\UserInterface;
use Tusk\Security\Contract\UserProviderInterface;
use Tusk\Contracts\Attributes\Service;

#[Service]
class SessionGuard implements GuardInterface
{
    private ?UserInterface $user = null;

    public function __construct(
        private readonly UserProviderInterface $userProvider
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?UserInterface
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $userId = $_SESSION['tuskpress_user_id'] ?? null;
        if ($userId) {
            $this->user = $this->userProvider->loadByIdentifier($userId);
            return $this->user;
        }

        return null;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    public function login(UserInterface $user): void
    {
        $this->setUser($user);
        $_SESSION['tuskpress_user_id'] = $user->getIdentifier();
    }

    public function logout(): void
    {
        $this->user = null;
        unset($_SESSION['tuskpress_user_id']);
    }
}
