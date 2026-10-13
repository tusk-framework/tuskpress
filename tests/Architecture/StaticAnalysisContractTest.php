<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class StaticAnalysisContractTest extends TestCase
{
    public function test_reviewed_baseline_passes_and_new_finding_is_rejected(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root.'/phpstan.neon', 'Static analysis has no reviewed regression configuration.');
        $fixture = tempnam(sys_get_temp_dir(), 'tuskpress-stan-');
        file_put_contents($fixture, '<?php function tuskpressRegressionProbe(): void { tuskpress_unknown_regression(); }');
        try {
            [$cleanExit, $cleanOutput] = $this->analyse(['src']);
            self::assertSame(0, $cleanExit, $cleanOutput);
            [$exit, $output] = $this->analyse(['src', $fixture]);
            self::assertSame(1, $exit, $output);
            self::assertStringContainsString('tuskpress_unknown_regression', $output);
        } finally {
            unlink($fixture);
        }
    }

    public function test_ci_runs_the_static_analysis_regression_gate(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/ci.yml');
        self::assertStringContainsString('run: composer analyse', $workflow);
        self::assertStringContainsString('pdo_sqlite', $workflow);
    }

    private function analyse(array $paths): array
    {
        $root = dirname(__DIR__, 2);
        $pipes = [];
        $process = proc_open([PHP_BINARY, $root.'/vendor/bin/phpstan', 'analyse', '--configuration='.$root.'/phpstan.neon', '--error-format=json', '--no-progress', ...$paths], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output.$errors];
    }
}
