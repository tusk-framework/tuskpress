<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

/**
 * PostMeta — Value Object representing a post metadata (wp_postmeta).
 */
readonly class PostMeta
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $postId,
        public readonly string $key,
        public readonly mixed  $value,
    ) {}

    /**
     * Deserializes the value if it is serialized in PHP format (WP compatibility).
     */
    public static function fromDatabase(int $id, int $postId, string $key, string $rawValue): self
    {
        $value = @unserialize($rawValue);
        if ($value === false && $rawValue !== 'b:0;') {
            $value = $rawValue; // It was not serialized
        }

        return new self($id, $postId, $key, $value);
    }

    /**
     * Serializes the value for storage (WP compatibility).
     */
    public function serializedValue(): string
    {
        return is_array($this->value) || is_object($this->value)
            ? serialize($this->value)
            : (string) $this->value;
    }
}
