<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Persistence;

use Tusk\Contracts\Attributes\Service;

use TuskPress\Infrastructure\Database\Connection;

/**
 * MySqlTermRepository — Manages terms/taxonomies (wp_terms, wp_term_taxonomy).
 * Stub implementation to complete bootstrap.
 */
#[Service]
class MySqlTermRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findByTaxonomy(string $taxonomy, int $perPage = 100): array
    {
        return $this->db->select(
            "SELECT t.*, tt.description, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = ?
             ORDER BY t.name ASC
             LIMIT ?",
            [$taxonomy, $perPage]
        );
    }

    public function findBySlug(string $slug, string $taxonomy): ?array
    {
        return $this->db->selectOne(
            "SELECT t.*, tt.description, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON t.term_id = tt.term_id
             WHERE t.slug = ? AND tt.taxonomy = ?",
            [$slug, $taxonomy]
        );
    }
}
