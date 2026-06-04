<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Database;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;
use Tusk\Contracts\Attributes\Factory;
use TuskPress\Infrastructure\Database\Connection;

#[Factory(provides: EntityManagerInterface::class, scope: 'singleton')]
class EntityManagerFactory
{
    public function __construct(private readonly Connection $connection) {}

    public function __invoke(): EntityManagerInterface
    {
        $paths = [__DIR__ . '/../../Domain'];
        $isDevMode = true;

        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

        $dbParams = [
            'driver'   => 'pdo_mysql',
            'host'     => \Tusk\Config\Env::get('DB_HOST', 'db'),
            'dbname'   => \Tusk\Config\Env::get('DB_NAME', 'wordpress'),
            'user'     => \Tusk\Config\Env::get('DB_USER', 'root'),
            'password' => \Tusk\Config\Env::get('DB_PASSWORD', ''),
            'charset'  => \Tusk\Config\Env::get('DB_CHARSET', 'utf8mb4'),
        ];

        $dbalConnection = DriverManager::getConnection($dbParams, $config);

        return new EntityManager($dbalConnection, $config);
    }
}
