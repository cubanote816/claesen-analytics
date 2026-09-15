<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Models\SyncHistory;
use Modules\Prospects\Traits\LogsSyncEvents;
use RuntimeException;
use Tests\TestCase;

/**
 * Concrete stub that exercises LogsSyncEvents without hitting external HTTP.
 */
class StubSyncCommand extends Command
{
    use LogsSyncEvents;

    protected $signature = 'test:stub-sync';

    protected $description = 'Test stub';

    public function handle(): void {}

    // Public test seams for the protected trait API (Slice A2 — logging-core).
    public function persistForTest(): void
    {
        $this->markPersisted();
    }

    public function failForTest(): void
    {
        $this->markFailed();
    }

    public function guardedForTest(callable $body): int
    {
        return $this->guardedSync($body);
    }
}

/**
 * Stub exercising the guardedSync failure lifecycle without external HTTP.
 */
class StubSyncCommandGuard extends StubSyncCommand
{
    protected $signature = 'test:stub-sync-guard';

    protected $description = 'Test stub exercising the guardedSync lifecycle';

    public function handleWithThrow(): void
    {
        $this->guardedSync(function (): void {
            throw new RuntimeException('Source unavailable');
        });
    }
}

class LogsSyncEventsTest extends TestCase
{
    use RefreshDatabase;

    private function stub(): StubSyncCommand
    {
        return new StubSyncCommand;
    }

    // -------------------------------------------------------------------------
    // startSyncLog — new record
    // -------------------------------------------------------------------------

    public function test_start_sync_log_creates_new_history_when_no_id_given(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);

        $this->assertDatabaseCount('prospects_sync_histories', 1);
        $this->assertDatabaseHas('prospects_sync_histories', [
            'command' => 'test:stub-sync',
            'status' => 'running',
        ]);
    }

    public function test_start_sync_log_sets_status_running_on_existing_record(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-lbfa-clubs',
            'status' => 'pending',
            'logs' => [],
        ]);

        $cmd = $this->stub();
        $cmd->startSyncLog(null, $history->id);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'id' => $history->id,
            'status' => 'running',
        ]);
        // Only the one record — no duplicate created
        $this->assertDatabaseCount('prospects_sync_histories', 1);
    }

    // -------------------------------------------------------------------------
    // LogsSyncEvents — append mode (historyId existente)
    // -------------------------------------------------------------------------

    public function test_start_sync_log_appends_to_existing_logs_without_overwrite(): void
    {
        // Simulate a chained command: first command wrote 2 logs and left history in running state
        $existingLogs = [
            ['time' => '10:00:00', 'message' => 'First command started', 'type' => 'info', 'icon' => '🚀'],
            ['time' => '10:00:01', 'message' => 'Record 1 synced', 'type' => 'info', 'icon' => '✅'],
        ];

        $history = SyncHistory::create([
            'command' => 'prospects:sync-lbfa-clubs',
            'status' => 'running',
            'logs' => $existingLogs,
        ]);

        $cmd = $this->stub();
        $cmd->startSyncLog(null, $history->id);
        // finish forces a full flush — all accumulated entries (2 existing + "Starting..." + "completed") persist
        $cmd->finishSyncLog(10);

        $fresh = $history->fresh();
        $this->assertSame('completed', $fresh->status);
        // logs must contain the 2 original entries plus the new ones (no overwrite)
        $messages = array_column($fresh->logs, 'message');
        $this->assertContains('First command started', $messages);
        $this->assertContains('Record 1 synced', $messages);
        $this->assertGreaterThanOrEqual(3, count($fresh->logs));
    }

    // -------------------------------------------------------------------------
    // logSyncEvent — flush every 5 events
    // -------------------------------------------------------------------------

    public function test_log_sync_event_flushes_to_db_every_five_events(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null); // creates history, logs[0] = "Starting..."

        $history = SyncHistory::where('command', 'test:stub-sync')->first();

        // Add 4 more events to reach the flush threshold (total accumulated = 5)
        $cmd->logSyncEvent('Event 1');
        $cmd->logSyncEvent('Event 2');
        $cmd->logSyncEvent('Event 3');
        $cmd->logSyncEvent('Event 4'); // This is the 5th accumulated → flush

        $fresh = $history->fresh();
        // Logs persisted to DB: starting + 4 = 5 entries
        $this->assertCount(5, $fresh->logs);
    }

    public function test_log_sync_event_does_not_flush_before_threshold(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null); // 1 log entry accumulated (no flush yet since 1 % 5 !== 0)

        $history = SyncHistory::where('command', 'test:stub-sync')->first();

        // Add 3 more — total 4, below flush threshold
        $cmd->logSyncEvent('Event 1');
        $cmd->logSyncEvent('Event 2');
        $cmd->logSyncEvent('Event 3');

        // DB should still have empty logs (no flush happened yet)
        $this->assertEmpty($history->fresh()->logs);
    }

    // -------------------------------------------------------------------------
    // finishSyncLog
    // -------------------------------------------------------------------------

    public function test_finish_sync_log_marks_completed_and_persists_logs(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);
        $cmd->finishSyncLog(42);

        $history = SyncHistory::where('command', 'test:stub-sync')->first();

        $this->assertSame('completed', $history->status);
        $this->assertSame(42, $history->records_count);
        $this->assertNotNull($history->finished_at);
        $this->assertNotEmpty($history->logs);

        $lastLog = last($history->logs);
        $this->assertStringContainsString('42', $lastLog['message']);
        $this->assertSame('success', $lastLog['type']);
    }

    // -------------------------------------------------------------------------
    // failSyncLog
    // -------------------------------------------------------------------------

    public function test_fail_sync_log_marks_failed_and_persists_logs(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);
        $cmd->failSyncLog('Connection timeout');

        $history = SyncHistory::where('command', 'test:stub-sync')->first();

        $this->assertSame('failed', $history->status);
        $this->assertNotNull($history->finished_at);
        $this->assertNotEmpty($history->logs);

        $lastLog = last($history->logs);
        $this->assertStringContainsString('Connection timeout', $lastLog['message']);
        $this->assertSame('error', $lastLog['type']);
    }

    // -------------------------------------------------------------------------
    // Slice A2 — immediate error persistence (no crash-loss window)
    // -------------------------------------------------------------------------

    public function test_error_log_flushes_immediately(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);

        // Stay below the 5-event batch threshold — the error must still be persisted.
        $cmd->logSyncEvent('Progress 1');
        $cmd->logSyncEvent('Progress 2');
        $cmd->logSyncEvent('Club ACME exploded', 'error', '❌');

        $history = SyncHistory::where('command', 'test:stub-sync')->first();
        $messages = array_column($history->fresh()->logs, 'message');
        $this->assertContains('Club ACME exploded', $messages);
    }

    public function test_flush_sync_log_persists_buffer_below_threshold(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);
        $cmd->logSyncEvent('Progress 1');
        $cmd->logSyncEvent('Progress 2');

        $cmd->flushSyncLog();

        $history = SyncHistory::where('command', 'test:stub-sync')->first();
        // Starting entry + 2 progress entries — flushed on demand, below the threshold.
        $this->assertCount(3, $history->fresh()->logs);
    }

    // -------------------------------------------------------------------------
    // Slice A2 — persisted / failed counters
    // -------------------------------------------------------------------------

    public function test_persisted_and_failed_counts_tracked(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);

        $cmd->persistForTest();
        $cmd->persistForTest();
        $cmd->failForTest();

        // records_count is fed with the persisted tally (persisted successes), not attempts.
        $cmd->finishSyncLog(2);

        $history = SyncHistory::where('command', 'test:stub-sync')->first();
        $this->assertSame(2, $history->records_count);

        $lastLog = last($history->logs);
        $this->assertStringContainsString('processed 3 | persisted 2 | failed 1', $lastLog['message']);
        $this->assertSame('success', $lastLog['type']);
    }

    public function test_start_sync_log_resets_counters(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);
        $cmd->persistForTest();
        $cmd->failForTest();

        // New run reusing the same history — counters must have been reset.
        $history = SyncHistory::where('command', 'test:stub-sync')->first();
        $cmd->startSyncLog(null, $history->id);

        $cmd->persistForTest();
        $cmd->finishSyncLog(1);

        $fresh = $history->fresh();
        $this->assertSame(1, $fresh->records_count);

        $lastLog = last($fresh->logs);
        // Without a reset this would read "processed 3 | persisted 2 | failed 1".
        $this->assertStringContainsString('processed 1 | persisted 1 | failed 0', $lastLog['message']);
    }

    // -------------------------------------------------------------------------
    // Slice A2 — guardedSync lifecycle (fail + rethrow / exit-code plumbing)
    // -------------------------------------------------------------------------

    public function test_guarded_sync_marks_failed_and_rethrows(): void
    {
        $cmd = new StubSyncCommandGuard;
        $cmd->startSyncLog(null, null);

        try {
            $cmd->handleWithThrow();
            $this->fail('Expected RuntimeException to propagate out of guardedSync');
        } catch (RuntimeException $e) {
            $this->assertSame('Source unavailable', $e->getMessage());
        }

        $history = SyncHistory::where('command', 'test:stub-sync-guard')->first();
        $this->assertSame('failed', $history->status);
        $this->assertNotNull($history->finished_at);

        $lastLog = last($history->logs);
        $this->assertSame('error', $lastLog['type']);
        $this->assertStringContainsString('Source unavailable', $lastLog['message']);
    }

    public function test_guarded_sync_returns_body_exit_code_without_marking_history(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);

        $exit = $cmd->guardedForTest(fn () => Command::FAILURE);

        $this->assertSame(Command::FAILURE, $exit);

        // The body did not finish or fail the history — the guard must not
        // falsely mark success (or failure) on the body's behalf.
        $history = SyncHistory::where('command', 'test:stub-sync')->first();
        $this->assertSame('running', $history->status);
        $this->assertNull($history->finished_at);
    }

    public function test_guarded_sync_success_path_preserves_normal_finish_behavior(): void
    {
        $cmd = $this->stub();
        $cmd->startSyncLog(null, null);

        // Integer body result is returned as-is.
        $this->assertSame(Command::SUCCESS, $cmd->guardedForTest(fn () => Command::SUCCESS));

        // A void body (handle() returning nothing) maps to SUCCESS, never a fake finish.
        $this->assertSame(Command::SUCCESS, $cmd->guardedForTest(function (): void {}));

        $history = SyncHistory::where('command', 'test:stub-sync')->first();
        $this->assertSame('running', $history->status);
        $this->assertNull($history->finished_at);
    }

    // -------------------------------------------------------------------------
    // Recovery actions — SyncHistory model (mark_failed / mark_completed)
    // -------------------------------------------------------------------------

    public function test_mark_failed_updates_pending_to_failed_with_log_appended(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-lbfa-clubs',
            'status' => 'pending',
            'logs' => [],
        ]);

        $logs = $history->logs ?? [];
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'message' => 'Manually marked as failed',
            'type' => 'error',
            'icon' => '🛑',
        ];

        $history->update([
            'status' => 'failed',
            'finished_at' => now(),
            'logs' => $logs,
        ]);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'id' => $history->id,
            'status' => 'failed',
        ]);

        $fresh = $history->fresh();
        $this->assertNotNull($fresh->finished_at);
        $this->assertCount(1, $fresh->logs);
        $this->assertSame('error', $fresh->logs[0]['type']);
    }

    public function test_mark_completed_updates_running_to_completed_with_log_appended(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-lbfa-clubs',
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        $logs = $history->logs ?? [];
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'message' => 'Manually marked as completed',
            'type' => 'success',
            'icon' => '✅',
        ];

        $history->update([
            'status' => 'completed',
            'finished_at' => now(),
            'logs' => $logs,
        ]);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'id' => $history->id,
            'status' => 'completed',
        ]);

        $fresh = $history->fresh();
        $this->assertNotNull($fresh->finished_at);
        $this->assertCount(1, $fresh->logs);
        $this->assertSame('success', $fresh->logs[0]['type']);
    }
}
