<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ComposerContractTest extends TestCase
{
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
