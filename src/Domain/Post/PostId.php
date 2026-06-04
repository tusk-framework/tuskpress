<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

/**
 * PostId — Value Object for the post ID.
 * Encapsulates the primitive type and guarantees domain semantics.
 */
readonly class PostId
{
    public function __construct(public readonly int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException("PostId must be a positive integer, received: {$value}");
        }
    }

    public function equals(PostId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
