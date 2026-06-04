<?php

declare(strict_types=1);

namespace TuskPress\Domain\User;

/**
 * UserId — Value Object for the user ID.
 */
readonly class UserId
{
    public function __construct(public readonly int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException("UserId must be a positive integer, received: {$value}");
        }
    }

    public function equals(UserId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
