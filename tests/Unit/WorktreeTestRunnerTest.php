<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * CLA-690 — bin/wt-test gives every worktree its own test database.
 *
 * All worktrees used to share claesen_analytics_web_testing, so two sessions
 * running RefreshDatabase at once dropped each other's tables ("Table
 * migrations doesn't exist"). bin/wt-test derives testing_<worktree-folder>,
 * creates it if missing and runs `php artisan test "$@"` in the Sail container
 * against it.
 *
 * These tests run the real script against a throwaway Git repository with a
 * linked worktree and a fake `docker` first on PATH that records every call,
 * so they need neither Docker nor MySQL.
 */
class WorktreeTestRunnerTest extends TestCase
{
    private const CONTAINER = 'claesen_api_web_oficial-laravel.test-1';

    private string $tmp;

    private string $main;

    private string $log;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/wt-test-'.bin2hex(random_bytes(6));
        $this->main = $this->tmp.'/main';
        $this->log = $this->tmp.'/docker.log';

        mkdir($this->tmp.'/bin', 0777, true);
        file_put_contents($this->tmp.'/bin/docker', <<<'SH'
            #!/usr/bin/env bash
            # Records one call per line, arguments separated by \x1f.
            ( IFS=$'\x1f'; printf '%s\n' "$*" ) >> "$FAKE_DOCKER_LOG"
            case "$*" in
              *wt-test-db.php*) exit "${FAKE_DOCKER_DB_EXIT:-0}" ;;
              *artisan*) exit "${FAKE_DOCKER_TEST_EXIT:-0}" ;;
            esac
            SH);
        chmod($this->tmp.'/bin/docker', 0755);

        mkdir($this->main);
        $this->git($this->main, 'init', '-q');
        $this->git($this->main, '-c', 'user.name=t', '-c', 'user.email=t@example.test', 'commit', '-q', '--allow-empty', '-m', 'init');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
    }

    public function test_a_worktree_gets_its_own_database_created_before_the_tests_run(): void
    {
        $worktree = $this->addWorktree('CLA-690.Feature');
        $db = 'testing_cla_690_feature_'.$this->hash($worktree);

        [$exit, , $stderr] = $this->runScript($worktree, ['--filter', 'Some Test', '--stop-on-failure']);

        $this->assertSame(0, $exit, $stderr);
        $this->assertSame([
            ['exec', '-u', 'sail', '-w', '/var/www/html/.worktrees/CLA-690.Feature', self::CONTAINER,
                'php', 'bin/wt-test-db.php', 'create', $db],
            ['exec', '-u', 'sail', '-w', '/var/www/html/.worktrees/CLA-690.Feature',
                '-e', 'DB_DATABASE='.$db, '-e', 'APP_LOCALE=en', self::CONTAINER,
                'php', 'artisan', 'test', '--filter', 'Some Test', '--stop-on-failure'],
        ], $this->dockerCalls());
    }

    public function test_the_main_checkout_uses_testing_main_with_its_path_hash(): void
    {
        $db = 'testing_main_'.$this->hash($this->main);
        [$exit, , $stderr] = $this->runScript($this->main, []);

        $this->assertSame(0, $exit, $stderr);
        $this->assertSame([
            ['exec', '-u', 'sail', '-w', '/var/www/html', self::CONTAINER,
                'php', 'bin/wt-test-db.php', 'create', $db],
            ['exec', '-u', 'sail', '-w', '/var/www/html',
                '-e', 'DB_DATABASE='.$db, '-e', 'APP_LOCALE=en', self::CONTAINER,
                'php', 'artisan', 'test'],
        ], $this->dockerCalls());
    }

    public function test_the_tests_exit_code_is_returned(): void
    {
        [$exit] = $this->runScript($this->addWorktree('cla-1'), [], ['FAKE_DOCKER_TEST_EXIT' => '3']);

        $this->assertSame(3, $exit);
    }

    public function test_tests_do_not_run_when_the_database_cannot_be_created(): void
    {
        $worktree = $this->addWorktree('cla-1');
        [$exit, , $stderr] = $this->runScript($worktree, [], ['FAKE_DOCKER_DB_EXIT' => '5']);

        $this->assertSame(5, $exit);
        $this->assertStringContainsString('testing_cla_1_'.$this->hash($worktree), $stderr);
        $this->assertCount(1, $this->dockerCalls());
    }

    public function test_drop_db_drops_the_worktree_database_and_runs_no_tests(): void
    {
        $worktree = $this->addWorktree('cla-1');
        [$exit, , $stderr] = $this->runScript($worktree, ['--drop-db']);

        $this->assertSame(0, $exit, $stderr);
        $this->assertSame([
            ['exec', '-u', 'sail', '-w', '/var/www/html/.worktrees/cla-1', self::CONTAINER,
                'php', 'bin/wt-test-db.php', 'drop', 'testing_cla_1_'.$this->hash($worktree)],
        ], $this->dockerCalls());
    }

    public function test_a_worktree_outside_the_main_checkout_is_refused_without_calling_docker(): void
    {
        $outside = $this->tmp.'/elsewhere';
        $this->git($this->main, 'worktree', 'add', '-q', '-b', 'elsewhere', $outside);

        [$exit, , $stderr] = $this->runScript($outside, []);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('not inside the main checkout', $stderr);
        $this->assertSame([], $this->dockerCalls());
    }

    public function test_long_folder_names_are_cut_to_the_mysql_identifier_limit(): void
    {
        $this->runScript($this->addWorktree(str_repeat('a', 70)), []);

        $database = $this->dockerCalls()[0][9];
        $this->assertMatchesRegularExpression('/^testing_a{47}_[0-9a-f]{8}$/', $database);
        $this->assertSame(64, strlen($database));
    }

    public function test_folders_that_sanitize_alike_or_are_named_main_get_distinct_databases(): void
    {
        $names = [];
        foreach ([$this->main, $this->addWorktree('cla-690'), $this->addWorktree('cla_690'), $this->addWorktree('main')] as $path) {
            $this->runScript($path, []);
            $names[] = $this->dockerCalls()[count($names) * 2][9];
        }

        $this->assertCount(4, array_unique($names), implode(', ', $names));
    }

    public function test_drop_db_with_other_arguments_is_refused_without_calling_docker(): void
    {
        [$exit, , $stderr] = $this->runScript($this->addWorktree('cla-1'), ['--drop-db', '--help']);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('--drop-db', $stderr);
        $this->assertSame([], $this->dockerCalls());
    }

    public function test_the_database_helper_refuses_names_outside_the_testing_prefix(): void
    {
        foreach (['claesen_analytics_web_testing', 'testing', 'testing_x`; DROP DATABASE y', 'testing_'] as $name) {
            exec('php '.escapeshellarg(dirname(__DIR__, 2).'/bin/wt-test-db.php').' drop '.escapeshellarg($name).' 2>&1', $output, $exit);

            $this->assertSame(2, $exit, $name);
        }

        exec('php '.escapeshellarg(dirname(__DIR__, 2).'/bin/wt-test-db.php').' truncate testing_x 2>&1', $output, $exit);
        $this->assertSame(2, $exit);
    }

    private function addWorktree(string $name): string
    {
        $path = $this->main.'/.worktrees/'.$name;
        $this->git($this->main, 'worktree', 'add', '-q', '-b', 'b-'.substr(md5($name), 0, 8), $path);

        return $path;
    }

    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     * @return array{int, string, string}
     */
    private function runScript(string $cwd, array $args, array $env = []): array
    {
        $process = proc_open(
            ['bash', dirname(__DIR__, 2).'/bin/wt-test', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
            [
                'PATH' => $this->tmp.'/bin:'.getenv('PATH'),
                'HOME' => $this->tmp,
                'FAKE_DOCKER_LOG' => $this->log,
            ] + $env,
        );
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /** @return list<list<string>> */
    private function dockerCalls(): array
    {
        if (! is_file($this->log)) {
            return [];
        }

        return array_map(
            fn (string $line) => explode("\x1f", $line),
            file($this->log, FILE_IGNORE_NEW_LINES),
        );
    }

    private function hash(string $checkout): string
    {
        exec('git -C '.escapeshellarg($checkout).' rev-parse --show-toplevel', $output);

        return substr(hash('sha256', $output[0]), 0, 8);
    }

    private function git(string $cwd, string ...$args): void
    {
        $command = 'git -C '.escapeshellarg($cwd).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1';
        exec($command, $output, $exit);
        if ($exit !== 0) {
            $this->fail("git failed: {$command}\n".implode("\n", $output));
        }
    }
}
