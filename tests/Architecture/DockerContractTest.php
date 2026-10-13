<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DockerContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_dockerfile_does_not_copy_local_engine_binary(): void
    {
        $dockerfile = file_get_contents($this->root.'/Dockerfile');

        self::assertIsString($dockerfile);
        self::assertStringContainsString('FROM ghcr.io/tusk-framework/tusk-engine:v0.3.4 AS tusk-engine', $dockerfile);
        self::assertStringContainsString('FROM ghcr.io/roadrunner-server/roadrunner:v2025.1.15 AS roadrunner', $dockerfile);
        self::assertStringContainsString('COPY --from=tusk-engine /usr/local/bin/tusk /usr/local/bin/tusk', $dockerfile);
        self::assertStringContainsString('COPY --from=roadrunner /usr/bin/rr /usr/bin/rr', $dockerfile);
        self::assertStringNotContainsString('COPY tusk-engine-', $dockerfile);
    }

    public function test_dockerfile_does_not_ignore_composer_platform_requirements(): void
    {
        $dockerfile = file_get_contents($this->root.'/Dockerfile');

        self::assertIsString($dockerfile);
        self::assertStringContainsString('FROM php:8.3-cli-bookworm', $dockerfile);
        self::assertStringContainsString('composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader', $dockerfile);
        self::assertStringContainsString('composer check-platform-reqs --no-dev', $dockerfile);
        self::assertStringNotContainsString('--ignore-platform-reqs', $dockerfile);
        self::assertStringContainsString('CMD ["tusk", "start"]', $dockerfile);
        self::assertStringNotContainsString('tusk", "start", "worker.php', $dockerfile);
    }

    public function test_compose_does_not_bind_mount_over_vendor(): void
    {
        $compose = file_get_contents($this->root.'/docker-compose.yml');

        self::assertIsString($compose);
        self::assertStringContainsString('mysql:8.4', $compose);
        self::assertStringContainsString('healthcheck:', $compose);
        self::assertStringContainsString('condition: service_healthy', $compose);
        self::assertStringContainsString('MYSQL_ROOT_PASSWORD:', $compose);
        self::assertStringContainsString('MYSQL_PASSWORD:', $compose);
        self::assertStringContainsString('tuskpress_db_data:/var/lib/mysql', $compose);
        self::assertStringContainsString('tuskpress_uploads:', $compose);
        self::assertStringNotContainsString('.:/app', $compose);
        self::assertStringNotContainsString('container_name:', $compose);
        self::assertStringNotContainsString('init.sql', $compose);
    }
}
