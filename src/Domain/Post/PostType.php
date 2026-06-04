<?php

declare(strict_types=1);

namespace TuskPress\Domain\Post;

/**
 * Post types enum — mirrors WordPress post types.
 */
enum PostType: string
{
    case Post       = 'post';
    case Page       = 'page';
    case Attachment = 'attachment';
    case Revision   = 'revision';
    case NavMenuItem = 'nav_menu_item';
    case CustomCss   = 'custom_css';
    case Customize_Changeset = 'customize_changeset';

    public function label(): string
    {
        return match ($this) {
            self::Post        => 'Post',
            self::Page        => 'Page',
            self::Attachment  => 'Media',
            self::Revision    => 'Revision',
            self::NavMenuItem => 'Menu item',
            self::CustomCss   => 'Custom CSS',
            self::Customize_Changeset => 'Customizer changeset',
        };
    }

    public function isPublic(): bool
    {
        return in_array($this, [self::Post, self::Page]);
    }

    public static function fromStringOrCustom(string $value): self|string
    {
        // Tries to map to the enum; if unrecognized, returns the string (custom post type)
        return self::tryFrom($value) ?? $value;
    }
}
