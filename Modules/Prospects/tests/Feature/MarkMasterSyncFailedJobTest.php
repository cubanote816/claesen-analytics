<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Jobs\MarkMasterSyncFailedJob;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

class MarkMasterSyncFailedJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Finding 8: when a job in the master sync chain fails, the master
     * SyncHistory must be marked failed — never left stuck in 'running'.
     *
     * MarkMasterSyncFailedJob is the catch handler wired into Bus::chain by
     * MasterSyncJob. It must flip a 'running' master history to 'failed',
     * record the error message in the logs, and set finished_at.
     */
    public function test_mark_master_sync_failed_job_marks_running_history_as_failed(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-master',
            'type' => 'master',
            'status' => 'running',
            'started_at' => now(),
            'logs' => [
                ['time' => '10:00:00', 'message' => 'Master started', 'type' => 'info', 'icon' => '🚀'],
            ],
        ]);

        (new MarkMasterSyncFailedJob(null, $history->id, 'ExecuteSyncJob failed: exit code 1'))->handle();

        $fresh = $history->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNotNull($fresh->finished_at);

        // The error must be appended to the logs, preserving prior entries
        $messages = array_column($fresh->logs, 'message');
        $this->assertContains('Master started', $messages);
        $this->assertTrue(
            collect($messages)->contains(fn ($m) => str_contains($m, 'ExecuteSyncJob failed')),
            'Error message must be appended to logs.'
        );
    }

    /**
     * Finding 8: a missing history record must not crash the catch handler —
     * the chain catch must remain robust even if the history was deleted.
     */
    public function test_mark_master_sync_failed_job_does_not_crash_on_missing_history(): void
    {
        // A non-existent history ID must not throw
        (new MarkMasterSyncFailedJob(null, 999999, 'irrelevant'))->handle();

        $this->assertDatabaseCount('prospects_sync_histories', 0);
    }
}
