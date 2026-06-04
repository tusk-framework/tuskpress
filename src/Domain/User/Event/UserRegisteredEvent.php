<?php

declare(strict_types=1);

namespace TuskPress\Domain\User\Event;

use TuskPress\Domain\User\User;

class UserRegisteredEvent
{
    public function __construct(public readonly User $user) {}
}
