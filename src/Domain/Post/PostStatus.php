<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

/**
 * Enum of a post's status — mirrors WordPress statuses.
 */
enum PostStatus: string
{
    case Publish   = 'publish';
    case Draft     = 'draft';
    case Pending   = 'pending';
    case Private   = 'private';
    case Trash     = 'trash';
    case AutoDraft = 'auto-draft';
    case Inherit   = 'inherit';
    case Future    = 'future';

    public function label(): string
    {
        return match ($this) {
            self::Publish   => 'Published',
            self::Draft     => 'Draft',
            self::Pending   => 'Pending Review',
            self::Private   => 'Private',
            self::Trash     => 'Trash',
            self::AutoDraft => 'Auto-Draft',
            self::Inherit   => 'Inherited',
            self::Future    => 'Scheduled',
        };
    }

    public function isVisible(): bool
    {
        return in_array($this, [self::Publish, self::Private]);
    }

    public function isPublic(): bool
    {
        return $this === self::Publish;
    }
}
