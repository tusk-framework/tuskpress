<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Persistence;

use Tusk\Contracts\Attributes\Service;

use TuskPress\Infrastructure\Database\Connection;

/**
 * MySqlCommentRepository — Manages comments (wp_comments).
 * Stub implementation to complete bootstrap.
 */
#[Service]
class MySqlCommentRepository
{
    public function __construct(private readonly Connection $db) {}

    public function count(string $status = 'approve'): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM {$this->db->table('comments')} WHERE comment_approved = ?",
            [$status]
        );
    }

    public function findAll(int $perPage = 20, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->select(
            "SELECT * FROM {$this->db->table('comments')}
             ORDER BY comment_date DESC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }
}
