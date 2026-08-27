<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Intelligence\Models\MirrorSyncRun;
use Modules\Intelligence\Services\SyncMirrorDataService;
use Tests\TestCase;

/**
 * CLA-404 — guards intelligence:sync-mirror against overlapping runs, and
 * persists a history of every run so the panel can show "last synced" and
 * gerencia doesn't have to trust an unattended cron silently (see the
 * 2026-08-20 incident: the mirror stalled 57 days with zero visibility).
 *
 * Exercises SyncMirrorDataService::startRun()/finishRun() directly rather
 * than syncAll() — the full sync reaches the real sqlsrv (ERP) connection,
 * which is unavailable and out of scope for this guard-only test.
 */
class MirrorSyncRunGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_run_is_blocked_when_a_run_is_already_in_progress(): void
    {
        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_RUNNING,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now(),
        ]);

        $run = app(SyncMirrorDataService::class)->startRun(MirrorSyncRun::SOURCE_MANUAL, 42);

        $this->assertNull($run);
        $this->assertSame(1, MirrorSyncRun::count());
    }

    public function test_a_completed_run_does_not_block_a_new_start(): void
    {
        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_COMPLETED,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay()->addMinutes(7),
        ]);

        $user = User::factory()->create();

        $run = app(SyncMirrorDataService::class)->startRun(MirrorSyncRun::SOURCE_MANUAL, $user->id);

        $this->assertNotNull($run);
        $this->assertSame(MirrorSyncRun::STATUS_RUNNING, $run->status);
        $this->assertSame(MirrorSyncRun::SOURCE_MANUAL, $run->trigger_source);
        $this->assertSame($user->id, $run->triggered_by_user_id);
        $this->assertSame(2, MirrorSyncRun::count());
    }

    public function test_a_failed_run_does_not_block_a_new_start(): void
    {
        MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_FAILED,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(2),
            'error_message' => 'Connection timed out',
        ]);

        $run = app(SyncMirrorDataService::class)->startRun();

        $this->assertNotNull($run);
        $this->assertSame(2, MirrorSyncRun::count());
    }

    public function test_duration_seconds_is_null_while_running(): void
    {
        $run = MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_RUNNING,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now(),
        ]);

        $this->assertNull($run->durationSeconds());
    }

    public function test_duration_seconds_is_computed_once_finished(): void
    {
        $run = MirrorSyncRun::create([
            'status' => MirrorSyncRun::STATUS_COMPLETED,
            'trigger_source' => MirrorSyncRun::SOURCE_SCHEDULED,
            'started_at' => now()->subMinutes(7),
            'finished_at' => now(),
        ]);

        $this->assertEqualsWithDelta(420, $run->durationSeconds(), 2);
    }
}
