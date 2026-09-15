<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Prospects\Jobs\ExecuteSyncJob;
use Modules\Prospects\Jobs\MasterSyncJob;
use Modules\Prospects\Jobs\SendMasterSyncFinishedNotificationJob;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

class MasterSyncJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Finding 8: MasterSyncJob must dispatch the full federation chain.
     *
     * This is the structural assertion: all seven ExecuteSyncJob instances plus
     * the final notification job are chained in the documented order.
     * The catch handler (MarkMasterSyncFailedJob) is unit-tested separately
     * in MarkMasterSyncFailedJobTest.
     */
    public function test_master_sync_job_dispatches_full_chain_in_order(): void
    {
        Bus::fake();

        $history = SyncHistory::create([
            'command' => 'prospects:sync-master',
            'type' => 'master',
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        (new MasterSyncJob(null, $history->id))->handle();

        Bus::assertChained([
            ExecuteSyncJob::class,
            ExecuteSyncJob::class,
            ExecuteSyncJob::class,
            ExecuteSyncJob::class,
            ExecuteSyncJob::class,
            ExecuteSyncJob::class,
            ExecuteSyncJob::class,
            SendMasterSyncFinishedNotificationJob::class,
        ]);
        Bus::assertDispatched(
            ExecuteSyncJob::class,
            fn (ExecuteSyncJob $job) => count($job->chainCatchCallbacks ?? []) === 1
        );
    }

    public function test_final_chain_job_marks_master_history_completed(): void
    {
        $history = SyncHistory::create([
            'command' => 'prospects:sync-master',
            'type' => 'master',
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        (new SendMasterSyncFinishedNotificationJob(null, $history->id))->handle();

        $history->refresh();
        $this->assertSame('completed', $history->status);
        $this->assertNotNull($history->finished_at);
    }
}
