<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline;

use Illuminate\Console\Scheduling\Schedule;
use Tests\Feature\ClaesenBaseline\Support\MatchesBaselineSnapshot;
use Tests\TestCase;

/**
 * Freezes the scheduler: which commands run, how often, and with which
 * overlap/queue guards.
 *
 * Several scheduled commands notify users selected purely by global Spatie role
 * (Safety compliance, Mailing deliverability, FieldOps alerts). Those recipient
 * queries become cross-organization leaks the moment a Bertels user holds one of
 * those roles, so the scheduler is part of the isolation surface and not just
 * operational trivia.
 */
final class ScheduleSnapshotTest extends TestCase
{
    use MatchesBaselineSnapshot;

    public function test_the_claesen_scheduler_matches_the_baseline(): void
    {
        $lines = [];

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            $lines[] = sprintf(
                '%s | expression=%s | timezone=%s | withoutOverlapping=%s | onOneServer=%s',
                $this->normalizeCommand($event->command ?? $event->getSummaryForDisplay()),
                $event->expression,
                $event->timezone ? (string) $event->timezone : '-',
                $event->withoutOverlapping ? 'yes' : 'no',
                $event->onOneServer ? 'yes' : 'no',
            );
        }

        sort($lines);

        $this->assertMatchesBaselineSnapshot('schedule', implode("\n", $lines));
    }

    private function normalizeCommand(string $command): string
    {
        // Strip the absolute PHP binary and artisan paths: they differ between
        // this worktree, CI and production.
        $command = preg_replace('#^\S*php\S*\s+#', 'php ', $command) ?? $command;

        return preg_replace("#'?[^'\\s]*artisan'?#", 'artisan', $command) ?? $command;
    }
}
