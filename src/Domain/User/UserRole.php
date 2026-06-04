<?php

declare(strict_types=1);

namespace TuskPress\Domain\User;

/**
 * UserRole — WordPress roles mapped as PHP 8.1+ enum
 */
enum UserRole: string
{
    case Administrator = 'administrator';
    case Editor        = 'editor';
    case Author        = 'author';
    case Contributor   = 'contributor';
    case Subscriber    = 'subscriber';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Editor        => 'Editor',
            self::Author        => 'Author',
            self::Contributor   => 'Contributor',
            self::Subscriber    => 'Subscriber',
        };
    }

    /**
     * Hierarchical level (compatible with WP user levels).
     */
    public function level(): int
    {
        return match ($this) {
            self::Administrator => 10,
            self::Editor        => 7,
            self::Author        => 2,
            self::Contributor   => 1,
            self::Subscriber    => 0,
        };
    }

    /**
     * Capabilities of this role (compatible with WP capabilities).
     * @return string[]
     */
    public function capabilities(): array
    {
        $base = [$this->value => true];

        return match ($this) {
            self::Administrator => array_merge($base, [
                'manage_options'  => true,
                'edit_posts'      => true,
                'publish_posts'   => true,
                'delete_posts'    => true,
                'edit_pages'      => true,
                'publish_pages'   => true,
                'upload_files'    => true,
                'manage_categories' => true,
                'moderate_comments' => true,
                'manage_plugins'  => true,
                'manage_themes'   => true,
                'manage_users'    => true,
                'create_users'    => true,
                'delete_users'    => true,
                'edit_users'      => true,
                'install_plugins' => true,
                'install_themes'  => true,
                'update_core'     => true,
            ]),
            self::Editor => array_merge($base, [
                'edit_posts'      => true,
                'publish_posts'   => true,
                'delete_posts'    => true,
                'edit_pages'      => true,
                'publish_pages'   => true,
                'upload_files'    => true,
                'manage_categories' => true,
                'moderate_comments' => true,
            ]),
            self::Author => array_merge($base, [
                'edit_posts'    => true,
                'publish_posts' => true,
                'upload_files'  => true,
                'delete_posts'  => true,
            ]),
            self::Contributor => array_merge($base, [
                'edit_posts'   => true,
            ]),
            self::Subscriber => array_merge($base, [
                'read' => true,
            ]),
        };
    }

    public function can(string $capability): bool
    {
        return $this->capabilities()[$capability] ?? false;
    }

    public function isAtLeast(UserRole $role): bool
    {
        return $this->level() >= $role->level();
    }
}
