<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * CLA-532: hermetic proof that the migrated config keys survive `config:cache`
 * without any `.env` — the values are baked from config/*.php at build time and
 * read back from the cache at runtime, never from runtime env().
 *
 * Both subprocesses run under `/usr/bin/env -i` so they inherit NOTHING from
 * the PHPUnit process; only an explicit allow-list of fake vars is injected.
 *   1. BUILD  — fake values + APP_CONFIG_CACHE redirected to a temp file; the
 *               runner propagates config:cache's real exit code.
 *   2. VERIFY — the temp cache file only; none of the fake vars.
 *
 * Nothing reads or copies .env / .env.testing / bootstrap/cache/config.php.
 * No database is used.
 */
class ConfigCacheEnvIndependenceTest extends TestCase
{
    private string $tmpDir = '';

    private string $emptyEnvDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir().'/cla532_'.bin2hex(random_bytes(6));
        $this->emptyEnvDir = $this->tmpDir.'/noenv';
        mkdir($this->emptyEnvDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Safety net; the test also cleans up + asserts removal itself.
        if ($this->tmpDir !== '') {
            $this->rrmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    public function test_migrated_config_keys_survive_config_cache_without_env(): void
    {
        // Hermetic isolation depends on `env -i`; a missing binary is a hard
        // failure of this gate, never a silent skip.
        $this->assertTrue(
            is_executable('/usr/bin/env'),
            '/usr/bin/env is required to run both subprocesses under a truly empty environment.',
        );

        $base = base_path();
        $cacheFile = $this->tmpDir.'/config.php';
        $runner = $this->tmpDir.'/runner.php';
        file_put_contents($runner, $this->runnerScript());

        $key = 'base64:'.base64_encode(str_repeat('a', 32));

        try {
            // 1. BUILD — fake process env, fully isolated via `env -i`.
            $build = $this->hermeticPhp([
                'PATH=/usr/bin:/bin',
                'APP_ENV=testing',
                'APP_KEY='.$key,
                'APP_CONFIG_CACHE='.$cacheFile,
                'MAILING_DRIVER=simulation',
                'MAIL_TO_ADDRESS=qa@example.test, second@example.test',
                'AZURE_GROUP_SUPER_ADMIN=guid-sa',
                'AZURE_GROUP_ADMIN=guid-admin',
                'AZURE_GROUP_FINANCE=guid-fin',
                'AZURE_GROUP_PM=guid-pm',
                'WATCHDOG_REPORT_EMAIL=wd@example.test',
                'WATCHDOG_VANGUARD_EMAIL=vanguard@example.test',
                'WATCHDOG_IMMEDIATE_THRESHOLD=999',
                'WATCHDOG_SYNC_YEARS_BACK=7',
                'WEBSITE_CONSULTATION_EMAIL=consult@example.test',
            ], ['build', $base, $this->emptyEnvDir], $base);

            $build->run();
            $this->assertSame(
                0,
                $build->getExitCode(),
                "config:cache failed:\n".$build->getErrorOutput().$build->getOutput(),
            );
            $this->assertFileExists($cacheFile);

            // 2. VERIFY — only what the runtime genuinely needs to boot + locate
            //    the cache; NONE of the fake domain vars.
            $verify = $this->hermeticPhp([
                'PATH=/usr/bin:/bin',
                'APP_ENV=testing',
                'APP_KEY='.$key,
                'APP_CONFIG_CACHE='.$cacheFile,
            ], ['verify', $base, $this->emptyEnvDir], $base);

            $verify->run();
            $this->assertSame(
                0,
                $verify->getExitCode(),
                "verify failed:\n".$verify->getErrorOutput().$verify->getOutput(),
            );

            $out = json_decode(trim($verify->getOutput()), true);
            $this->assertIsArray($out, 'verify output: '.$verify->getOutput());

            $this->assertTrue($out['configuration_is_cached']);
            $this->assertSame('simulation', $out['app.mailing_driver']);
            $this->assertSame(['qa@example.test', 'second@example.test'], $out['mail.always_to']);
            $this->assertSame([
                'guid-sa' => 'super_admin',
                'guid-admin' => 'admin',
                'guid-fin' => 'financial_manager',
                'guid-pm' => 'project_manager',
            ], $out['core.azure_role_mapping']);
            $this->assertSame('wd@example.test', $out['performance.watchdog.report_email']);
            $this->assertSame('vanguard@example.test', $out['performance.watchdog.vanguard_email']);
            $this->assertSame(999, $out['performance.watchdog.immediate_threshold']);
            $this->assertSame(7, $out['performance.watchdog.sync_years_back']);
            $this->assertSame('consult@example.test', $out['website.consultation_notification_email']);
        } finally {
            $this->rrmdir($this->tmpDir);
        }

        $this->assertDirectoryDoesNotExist($this->tmpDir);
    }

    /**
     * @param  array<int, string>  $envAssignments  NAME=VALUE strings
     * @param  array<int, string>  $args
     */
    private function hermeticPhp(array $envAssignments, array $args, string $cwd): Process
    {
        $command = array_merge(
            ['/usr/bin/env', '-i'],
            $envAssignments,
            [PHP_BINARY, $this->tmpDir.'/runner.php'],
            $args,
        );

        return new Process($command, $cwd, timeout: 120);
    }

    private function runnerScript(): string
    {
        return <<<'PHP'
<?php
[$mode, $base, $envDir] = [$argv[1], $argv[2], $argv[3]];

require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->useEnvironmentPath($envDir);   // empty dir -> no .env is ever read

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

if ($mode === 'build') {
    exit($kernel->call('config:cache'));   // propagate the real exit code
}

$kernel->bootstrap();
echo json_encode([
    'configuration_is_cached'                  => $app->configurationIsCached(),
    'app.mailing_driver'                       => config('app.mailing_driver'),
    'mail.always_to'                           => config('mail.always_to'),
    'core.azure_role_mapping'                  => config('core.azure_role_mapping'),
    'performance.watchdog.report_email'        => config('performance.watchdog.report_email'),
    'performance.watchdog.vanguard_email'      => config('performance.watchdog.vanguard_email'),
    'performance.watchdog.immediate_threshold' => config('performance.watchdog.immediate_threshold'),
    'performance.watchdog.sync_years_back'     => config('performance.watchdog.sync_years_back'),
    'website.consultation_notification_email'  => config('website.consultation_notification_email'),
]);
PHP;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
