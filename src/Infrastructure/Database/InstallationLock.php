<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/** Serialize first-run installers across workers before any schema writes. */
final class InstallationLock
{
    private ?string $mysqlKey = null;
    /** @var resource|null */
    private $file = null;

    public function __construct(private readonly Connection $connection) {}

    public function acquire(): bool
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $key = 'tuskpress-install-'.hash('md5', (string) ($this->connection->getParams()['dbname'] ?? ''));
            $result = $this->connection->fetchOne('SELECT GET_LOCK(?, 0)', [$key]);
            if ($result === null) {
                throw new \RuntimeException('Unable to acquire installation lock.');
            }
            if ((int) $result !== 1) {
                return false;
            }
            $this->mysqlKey = $key;
            return true;
        }
        if ($platform instanceof SQLitePlatform) {
            $path = (string) ($this->connection->getParams()['path'] ?? ':memory:');
            $file = fopen(sys_get_temp_dir().'/tuskpress-install-'.hash('sha256', $path).'.lock', 'c');
            if ($file === false) {
                throw new \RuntimeException('Unable to open installation lock.');
            }
            $this->file = $file;
            return flock($this->file, LOCK_EX | LOCK_NB);
        }
        throw new \RuntimeException('Unsupported installation database.');
    }

    public function release(): void
    {
        if ($this->mysqlKey !== null) {
            $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$this->mysqlKey]);
            $this->mysqlKey = null;
        }
        if ($this->file !== null) {
            flock($this->file, LOCK_UN);
            fclose($this->file);
            $this->file = null;
        }
    }
}
