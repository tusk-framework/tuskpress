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
        self::assertStringContainsString('FROM ghcr.io/roadrunner-server/roadrunner:2025.1.15 AS roadrunner', $dockerfile);
        self::assertStringNotContainsString('ghcr.io/roadrunner-server/roadrunner:v2025.1.15', $dockerfile);
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

    public function test_dockerfile_installs_sockets_for_locked_composer_dependencies(): void
    {
        $dockerfile = file_get_contents($this->root.'/Dockerfile');

        self::assertIsString($dockerfile);
        self::assertStringContainsString('docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql sockets', $dockerfile);
    }

    public function test_dockerfile_installs_archive_extractor_for_composer_dist_archives(): void
    {
        $dockerfile = file_get_contents($this->root.'/Dockerfile');

        self::assertIsString($dockerfile);
        self::assertStringContainsString('apt-get install -y --no-install-recommends libonig-dev unzip', $dockerfile);
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

    public function test_web_service_has_dependency_free_healthcheck_and_smoke_waits_for_health(): void
    {
        $compose = file_get_contents($this->root.'/docker-compose.yml');
        $smoke = file_get_contents($this->root.'/tests/Smoke/compose-smoke.sh');

        self::assertIsString($compose);
        self::assertIsString($smoke);
        self::assertMatchesRegularExpression('/web:\n(?:(?!\n  mysql:).)*healthcheck:\n(?:(?!\n  mysql:).)*php.*healthz/s', $compose);
        self::assertStringContainsString('up --detach --build --wait --wait-timeout', $smoke);
        self::assertStringContainsString('healthz)', $smoke);
        self::assertStringContainsString('down --volumes --remove-orphans', $smoke);
    }

    public function test_smoke_logs_in_after_install_and_verifies_redirect_and_admin_session(): void
    {
        $smoke = file_get_contents($this->root.'/tests/Smoke/compose-smoke.sh');

        self::assertIsString($smoke);
        self::assertStringContainsString('--data-urlencode "log=${admin_user}"', $smoke);
        self::assertStringContainsString('--data-urlencode "pwd=${admin_password}"', $smoke);
        self::assertStringContainsString("--data-urlencode 'redirect_to=wp-admin'", $smoke);
        self::assertStringContainsString('[[ "$login_status" == "302" ]]', $smoke);
        self::assertStringContainsString('[[ "$login_location" == "http://127.0.0.1:8080/wp-admin" ]]', $smoke);
        self::assertStringContainsString('--cookie "$cookie_file" --cookie-jar "$cookie_file"', $smoke);
        self::assertStringContainsString('[[ "$admin_response" == *"class=\"user-role\">Administrator"* ]]', $smoke);
    }

    public function test_smoke_login_post_occurs_once_and_captures_status_and_redirect_together(): void
    {
        $smoke = file_get_contents($this->root.'/tests/Smoke/compose-smoke.sh');

        self::assertIsString($smoke);
        self::assertSame(1, preg_match_all('/http:\/\/127\.0\.0\.1:8080\/wp-login/', $smoke));
        self::assertStringContainsString('login_result="$(curl --silent --show-error --output /dev/null --cookie "$cookie_file" --cookie-jar "$cookie_file"', $smoke);
        self::assertStringContainsString("--write-out '%{http_code}|%{redirect_url}'", $smoke);
        self::assertStringContainsString("IFS='|' read -r login_status login_location <<< \"\$login_result\"", $smoke);
    }

    public function test_smoke_validates_project_identity_from_compose_json_config(): void
    {
        $smoke = file_get_contents($this->root.'/tests/Smoke/compose-smoke.sh');

        self::assertIsString($smoke);
        self::assertStringContainsString('config --format json', $smoke);
        self::assertStringContainsString('["name"]', $smoke);
        self::assertStringContainsString('[[ "$configured" == "$project" && "$project" =~ $project_pattern ]]', $smoke);
        self::assertStringNotContainsString('config --project-name', $smoke);
    }
}
