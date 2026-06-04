<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Database;

use Tusk\Contracts\Attributes\Service;

/**
 * Connection — PDO database connection wrapper.
 *
 * In Tusk Engine (persistent) mode, this connection stays in memory
 * and is reused between requests — without reconnection overhead.
 */
#[Service]
class Connection
{
    private \PDO $pdo;
    private string $tablePrefix = 'wp_';

    public function __construct() {
        $host = defined('DB_HOST') ? DB_HOST : (\Tusk\Config\Env::get('DB_HOST', '127.0.0.1'));
        $database = defined('DB_NAME') ? DB_NAME : (\Tusk\Config\Env::get('DB_NAME', 'wordpress'));
        $username = defined('DB_USER') ? DB_USER : (\Tusk\Config\Env::get('DB_USER', 'root'));
        $password = defined('DB_PASSWORD') ? DB_PASSWORD : (\Tusk\Config\Env::get('DB_PASSWORD', ''));
        $charset = defined('DB_CHARSET') ? DB_CHARSET : (\Tusk\Config\Env::get('DB_CHARSET', 'utf8mb4'));
        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

        $this->pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ]);

        global $table_prefix;
        $this->tablePrefix = $table_prefix ?? 'wp_';
    }

    // =========================================================
    // Query helpers
    // =========================================================

    /**
     * Executes a SELECT query and returns all results.
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /**
     * Executes a SELECT query and returns only the first result.
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Executes an INSERT and returns the generated ID.
     */
    public function insert(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Executes UPDATE/DELETE and returns the number of affected rows.
     */
    public function statement(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * Returns the value of a single column of the first row.
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    // =========================================================
    // Transactions
    // =========================================================

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================
    // Table name helper (respects the configured prefix)
    // =========================================================

    public function table(string $name): string
    {
        // Removes duplicate prefix if it already has it
        if (str_starts_with($name, $this->tablePrefix)) {
            return $name;
        }
        return $this->tablePrefix . $name;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
