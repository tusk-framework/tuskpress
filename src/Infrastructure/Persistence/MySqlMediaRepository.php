<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Persistence;

use Tusk\Contracts\Attributes\Service;

use TuskPress\Infrastructure\Database\Connection;

/**
 * MySqlMediaRepository — Manages attachments (wp_posts with post_type=attachment).
 * Stub implementation to complete bootstrap.
 */
#[Service]
class MySqlMediaRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findAll(int $perPage = 20, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->select(
            "SELECT * FROM {$this->db->table('posts')}
             WHERE post_type = 'attachment' AND post_status != 'trash'
             ORDER BY post_date DESC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM {$this->db->table('posts')}
             WHERE post_type = 'attachment' AND post_status != 'trash'"
        );
    }
}
