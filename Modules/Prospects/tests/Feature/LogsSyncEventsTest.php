<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Models\SyncHistory;
use Modules\Prospects\Traits\LogsSyncEvents;
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
}

class LogsSyncEventsTest extends TestCase
{
    use RefreshDatabase;

    private function stub(): StubSyncCommand
    {
        return new StubSyncCommand();
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
            'status'  => 'running',
        ]);
    }

    public function test_start_sync_log_sets_status_running_on_existing_record(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-lbfa-clubs',
            'status'  => 'pending',
            'logs'    => [],
        ]);

        $cmd = $this->stub();
        $cmd->startSyncLog(null, $history->id);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'id'     => $history->id,
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
            'status'  => 'running',
            'logs'    => $existingLogs,
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
    // Recovery actions — SyncHistory model (mark_failed / mark_completed)
    // -------------------------------------------------------------------------

    public function test_mark_failed_updates_pending_to_failed_with_log_appended(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-lbfa-clubs',
            'status'  => 'pending',
            'logs'    => [],
        ]);

        $logs   = $history->logs ?? [];
        $logs[] = [
            'time'    => now()->format('H:i:s'),
            'message' => 'Manually marked as failed',
            'type'    => 'error',
            'icon'    => '🛑',
        ];

        $history->update([
            'status'      => 'failed',
            'finished_at' => now(),
            'logs'        => $logs,
        ]);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'id'     => $history->id,
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
            'command'    => 'prospects:sync-lbfa-clubs',
            'status'     => 'running',
            'started_at' => now(),
            'logs'       => [],
        ]);

        $logs   = $history->logs ?? [];
        $logs[] = [
            'time'    => now()->format('H:i:s'),
            'message' => 'Manually marked as completed',
            'type'    => 'success',
            'icon'    => '✅',
        ];

        $history->update([
            'status'      => 'completed',
            'finished_at' => now(),
            'logs'        => $logs,
        ]);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'id'     => $history->id,
            'status' => 'completed',
        ]);

        $fresh = $history->fresh();
        $this->assertNotNull($fresh->finished_at);
        $this->assertCount(1, $fresh->logs);
        $this->assertSame('success', $fresh->logs[0]['type']);
    }
}
