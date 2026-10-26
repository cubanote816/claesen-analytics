<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline\Support;

/**
 * Minimal snapshot helper for the Claesen regression baseline (multi-organization F0).
 *
 * The snapshots freeze observable surfaces of the Claesen-only backoffice
 * (routes, scheduler, Filament panel registry) so every later multi-organization
 * phase has to prove it changed nothing it did not declare.
 *
 * Snapshots are committed. They are never written implicitly: a missing or
 * outdated snapshot fails the test and asks for an explicit regeneration with
 * UPDATE_BASELINE_SNAPSHOTS=1, so a real regression can never silently
 * overwrite the baseline during a normal test run.
 */
trait MatchesBaselineSnapshot
{
    protected function assertMatchesBaselineSnapshot(string $name, string $actual): void
    {
        $path = __DIR__.'/../snapshots/'.$name.'.txt';
        $actual = rtrim($actual)."\n";

        if ($this->shouldUpdateBaselineSnapshots()) {
            file_put_contents($path, $actual);
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertFileExists(
            $path,
            "Baseline snapshot [{$name}] is missing. Regenerate it on purpose with "
            .'UPDATE_BASELINE_SNAPSHOTS=1 and commit the result.'
        );

        $this->assertSame(
            file_get_contents($path),
            $actual,
            "The Claesen baseline snapshot [{$name}] changed. If the change is an intended, "
            .'declared part of the current multi-organization phase, regenerate it with '
            .'UPDATE_BASELINE_SNAPSHOTS=1 and review the diff in the pull request. '
            .'Otherwise this is a regression against the Claesen-only behaviour.'
        );
    }

    private function shouldUpdateBaselineSnapshots(): bool
    {
        return filter_var(
            $_SERVER['UPDATE_BASELINE_SNAPSHOTS'] ?? $_ENV['UPDATE_BASELINE_SNAPSHOTS'] ?? getenv('UPDATE_BASELINE_SNAPSHOTS') ?: '',
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
