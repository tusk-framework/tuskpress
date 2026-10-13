<?php

declare(strict_types=1);

namespace TuskPress\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SmokeLogRetentionTest extends TestCase
{
    public function test_failing_smoke_prints_and_retains_diagnostics_before_scoped_cleanup(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir().'/tuskpress-smoke-contract-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $environment = getenv();
        $originalPath = getenv('PATH');
        foreach (array_keys($environment) as $key) {
            if (strcasecmp($key, 'PATH') === 0) { unset($environment[$key]); }
        }
        $shellPath = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/usr/bin'.PATH_SEPARATOR : '';
        $environment['PATH'] = $root.'/tests/Smoke/fixtures/bin'.PATH_SEPARATOR.$shellPath.$originalPath;
        $environment['TMPDIR'] = str_replace('\\', '/', $directory);
        $environment['TUSKPRESS_SMOKE_LOG_DIR'] = str_replace('\\', '/', $directory.'/artifacts');
        $environment['SMOKE_TEST_TRACE'] = str_replace('\\', '/', $directory.'/trace');
        $bash = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash';
        $pipes = [];
        try {
            $process = proc_open([$bash, $root.'/tests/Smoke/compose-smoke.sh'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(42, proc_close($process), $output);
            self::assertStringContainsString('SMOKE_FAILURE_DIAGNOSTIC_MARKER', $output, 'Failure diagnostics were not printed.');
            $logs = glob($directory.'/artifacts/*.logs') ?: [];
            self::assertCount(1, $logs, 'Logs were not placed in the artifact directory.');
            self::assertStringContainsString('SMOKE_FAILURE_DIAGNOSTIC_MARKER', file_get_contents($logs[0]));
            self::assertMatchesRegularExpression('/^tuskpress-smoke-[0-9]{14}-[0-9]+-[0-9]+\s*$/', file_get_contents($directory.'/trace'));
        } finally {
            foreach (glob($directory.'/artifacts/*') ?: [] as $file) { unlink($file); }
            if (is_dir($directory.'/artifacts')) { rmdir($directory.'/artifacts'); }
            foreach (glob($directory.'/*') ?: [] as $file) { unlink($file); }
            rmdir($directory);
        }
    }
}
