<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Health;

use Doctrine\ORM\EntityManagerInterface;
use Tusk\Cloud\Health\HealthCheckInterface;
use Tusk\Contracts\Attributes\Service;

#[Service(provides: HealthCheckInterface::class)]
class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function getName(): string
    {
        return 'database';
    }

    public function check(): bool
    {
        try {
            $conn = $this->em->getConnection();
            $conn->connect();
            return $conn->isConnected();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
