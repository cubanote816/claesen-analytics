<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * CLA-662 — pins the order of the last steps of infrastructure/scripts/deploy.sh.
 *
 * In the PR #41 deploy a `supervisorctl restart` of the queue workers hung for
 * about 10 minutes with the app in maintenance mode. The fix: queue:restart
 * before `artisan up`, `up` before any supervisorctl call, never restart the
 * workers, and bound every supervisorctl call with `timeout`. No CI runs the
 * script itself, so this keeps an edit from silently undoing that order.
 *
 * Note: deploy.yml runs the server copy /opt/claesen/scripts/deploy.sh; a
 * change here only takes effect once it is installed there.
 */
class DeployScriptOrderTest extends TestCase
{
    /** @var list<array{int, string}> */
    private array $commands;

    protected function setUp(): void
    {
        $this->commands = self::commands(dirname(__DIR__, 2).'/infrastructure/scripts/deploy.sh');
    }

    public function test_queue_restart_runs_before_the_app_leaves_maintenance_mode(): void
    {
        $this->assertLessThan(
            $this->lineOf('/artisan" up'),
            $this->lineOf('/artisan" queue:restart'),
        );
    }

    public function test_every_supervisorctl_call_comes_after_up_and_is_bounded_by_timeout(): void
    {
        $up = $this->lineOf('/artisan" up');
        $calls = $this->supervisorCalls();

        $this->assertNotEmpty($calls);
        foreach ($calls as [$line, $command]) {
            $this->assertGreaterThan($up, $line, "Runs before `up`: {$command}");
            $this->assertMatchesRegularExpression('/^timeout \d+ /', $command, "No timeout: {$command}");
        }
    }

    public function test_queue_workers_are_started_never_restarted(): void
    {
        foreach ($this->supervisorCalls() as [, $command]) {
            $this->assertDoesNotMatchRegularExpression(
                '/supervisorctl\s+restart\b.*claesen-worker/',
                $command,
                "Restarts the queue workers: {$command}",
            );
        }

        $this->assertCount(1, $this->matching("supervisorctl start 'claesen-worker:*'"));
        $this->assertCount(1, $this->matching('supervisorctl restart claesen-scheduler'));
    }

    public function test_supervisor_failures_warn_instead_of_being_swallowed(): void
    {
        $tail = array_filter($this->commands, fn (array $c) => $c[0] > $this->lineOf('/artisan" up'));
        $warnings = array_filter($tail, fn (array $c) => str_contains($c[1], 'ADVERTENCIA'));

        // Worker start timeout, scheduler restart and final status.
        $this->assertCount(3, $warnings);
    }

    private function lineOf(string $needle): int
    {
        $found = $this->matching($needle);
        $this->assertCount(1, $found, "Expected exactly one command containing {$needle}");

        return $found[0][0];
    }

    /**
     * Commands that invoke supervisorctl (warning texts that mention it are echoes).
     *
     * @return list<array{int, string}>
     */
    private function supervisorCalls(): array
    {
        return array_values(array_filter(
            $this->matching('supervisorctl '),
            fn (array $c) => ! str_starts_with($c[1], 'echo '),
        ));
    }

    /** @return list<array{int, string}> */
    private function matching(string $needle): array
    {
        return array_values(array_filter($this->commands, fn (array $c) => str_contains($c[1], $needle)));
    }

    /**
     * Non-comment commands with backslash continuations joined, keyed by the
     * line on which each command starts.
     *
     * @return list<array{int, string}>
     */
    private static function commands(string $path): array
    {
        $commands = [];
        $buffer = null;
        $start = 0;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $raw) {
            $line = trim($raw);
            if ($buffer === null && ($line === '' || str_starts_with($line, '#'))) {
                continue;
            }
            if ($buffer === null) {
                $buffer = '';
                $start = $index + 1;
            }
            $continues = str_ends_with($line, '\\');
            $buffer .= ($buffer === '' ? '' : ' ').rtrim($line, '\\ ');
            if (! $continues) {
                $commands[] = [$start, $buffer];
                $buffer = null;
            }
        }

        return $commands;
    }
}
