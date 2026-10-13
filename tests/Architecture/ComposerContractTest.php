<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ComposerContractTest extends TestCase
{
    public function test_ci_has_a_read_only_compose_smoke_gate_after_the_php_matrix(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root.'/.github/workflows/ci.yml');
        $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsString($workflow);
        self::assertStringContainsString("permissions:\n  contents: read", $workflow);
        self::assertStringContainsString("php: ['8.2', '8.5']", $workflow);
        self::assertStringContainsString('extensions: fileinfo, mbstring, pdo_mysql, sockets', $workflow);
        self::assertMatchesRegularExpression('/smoke:\n(?:(?!\n  [a-zA-Z0-9_-]+:).)*needs: test/s', $workflow);
        self::assertMatchesRegularExpression('/smoke:\n(?:(?!\n  [a-zA-Z0-9_-]+:).)*runs-on: ubuntu-latest/s', $workflow);
        self::assertMatchesRegularExpression('/smoke:\n(?:(?!\n  [a-zA-Z0-9_-]+:).)*MYSQL_DATABASE:\s*\S+.*MYSQL_USER:\s*\S+.*MYSQL_PASSWORD:\s*\S+.*MYSQL_ROOT_PASSWORD:\s*\S+/s', $workflow);
        self::assertSame(2, preg_match_all('/extensions: fileinfo, mbstring, pdo_mysql,(?: pdo_sqlite,)? sockets/', $workflow));
        self::assertStringContainsString('actions/upload-artifact@v4', $workflow);
        self::assertStringContainsString('if: always()', $workflow);
        self::assertMatchesRegularExpression('/- name: Run Docker Compose smoke test\n\s+env:\n\s+TUSKPRESS_SMOKE_LOG_DIR: \$\{\{ runner\.temp \}\}\/tuskpress-smoke-logs\n\s+run: composer test:smoke/', $workflow);
        self::assertMatchesRegularExpression('/- name: Check Docker Compose smoke prerequisites\n\s+run: \|\n(?:(?!\n      - name:).)*(?:docker info)(?:(?!\n      - name:).)*(?:docker compose version)(?:(?!\n      - name:).)*(?:curl --version)(?:(?!\n      - name:).)*(?:php --version)(?:(?!\n      - name:).)*(?:command -v seq)(?:(?!\n      - name:).)*(?:command -v sleep)/s', $workflow);
        self::assertMatchesRegularExpression('/Check Docker Compose smoke prerequisites.*?composer test:smoke/s', $workflow);
        self::assertStringContainsString('composer test:smoke', $workflow);
        self::assertSame('bash tests/Smoke/compose-smoke.sh', $manifest['scripts']['test:smoke'] ?? null);
    }

    public function test_project_requires_the_published_stable_framework_package(): void
    {
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $lock = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('^1.0', $manifest['require']['tusk-framework/framework'] ?? null);
        self::assertArrayNotHasKey('tusk/framework', $manifest['require']);

        $frameworkPackages = array_values(array_filter(
            $lock['packages'],
            static fn (array $package): bool => $package['name'] === 'tusk-framework/framework',
        ));

        self::assertCount(1, $frameworkPackages);
        self::assertMatchesRegularExpression('/^v?1\.\d+\.\d+$/', $frameworkPackages[0]['version']);
        self::assertArrayNotHasKey('repositories', $manifest);
        self::assertArrayNotHasKey('minimum-stability', $manifest);
    }
}
