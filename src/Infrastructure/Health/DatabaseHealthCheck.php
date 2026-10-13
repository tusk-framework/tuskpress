<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Health;

use TuskPress\Infrastructure\Database\EntityManagerFactory;
use Tusk\Cloud\Health\HealthCheckInterface;
use Tusk\Contracts\Attributes\Service;

#[Service]
class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly EntityManagerFactory $factory) {}

    public function getName(): string
    {
        return 'database';
    }

    public function check(): bool
    {
        $conn = null;
        try {
            $conn = ($this->factory)()->getConnection();
            return (int) $conn->fetchOne('SELECT 1') === 1;
        } catch (\Throwable $e) {
            return false;
        } finally {
            $conn?->close();
        }
    }
}
