<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;
use Modules\Prospects\Jobs\ExecuteSyncJob;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

class ExecuteSyncJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Finding 7: ExecuteSyncJob must check the Artisan::call exit code.
     *
     * When a sync command returns a non-zero exit code, the job must mark its
     * SyncHistory as failed and throw — instead of silently sending a success
     * notification. Without this, a failing sync looks successful on the
     * dashboard and the master chain continues as if nothing happened.
     */
    public function test_execute_sync_job_marks_history_failed_when_artisan_returns_non_zero_exit_code(): void
    {
        // Register a command that fails (exit code 1)
        Artisan::command('test:sync-fail {--user=} {--history=}', function () {
            return Command::FAILURE;
        });

        $history = SyncHistory::create([
            'command' => 'test:sync-fail',
            'type' => 'individual',
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        $job = new ExecuteSyncJob('test:sync-fail', null, $history->id);

        // The job must throw on non-zero exit code so the Bus::chain catch fires
        try {
            $job->handle();
            $this->fail('ExecuteSyncJob should have thrown on non-zero exit code.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('test:sync-fail', $e->getMessage());
        }

        // The SyncHistory must be marked failed, not left running
        $this->assertSame('failed', $history->fresh()->status);
        $this->assertNotNull($history->fresh()->finished_at);
    }

    /**
     * Finding 7 green path: a zero exit code preserves the existing behavior.
     * The SyncHistory must NOT be marked failed by the job on success.
     */
    public function test_execute_sync_job_preserves_success_path_on_zero_exit_code(): void
    {
        // Register a command that succeeds (exit code 0)
        Artisan::command('test:sync-ok {--user=} {--history=}', function () {
            return Command::SUCCESS;
        });

        $user = User::factory()->create();
        $history = SyncHistory::create([
            'command' => 'test:sync-ok',
            'type' => 'individual',
            'status' => 'running',
            'started_at' => now(),
            'logs' => [],
        ]);

        $job = new ExecuteSyncJob('test:sync-ok', $user->id, $history->id);

        // Must not throw on success
        $job->handle();

        // The job must not have marked it failed (the command itself owns completion)
        $this->assertNotSame('failed', $history->fresh()->status);

        $notificationData = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->value('data');
        $this->assertNotNull($notificationData);
        $this->assertStringContainsString('satisfactoriamente', $notificationData);
    }

    /**
     * Finding 7: on failure, a user-triggered sync must not receive the
     * "satisfactoriamente" success notification. We assert no success
     * database notification is created for the user on failure.
     */
    public function test_execute_sync_job_does_not_send_success_notification_on_failure(): void
    {
        Artisan::command('test:sync-fail-notify {--user=} {--history=}', function () {
            return Command::FAILURE;
        });

        $user = User::factory()->create();
        $history = SyncHistory::create([
            'command' => 'test:sync-fail-notify',
            'type' => 'individual',
            'status' => 'running',
            'started_at' => now(),
            'user_id' => $user->id,
            'logs' => [],
        ]);

        $job = new ExecuteSyncJob('test:sync-fail-notify', $user->id, $history->id);

        try {
            $job->handle();
        } catch (\RuntimeException $e) {
            // expected
        }

        $notificationData = DB::table('notifications')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->value('data');

        $this->assertNotNull($notificationData);
        $this->assertStringContainsString('errores', $notificationData);
        $this->assertStringNotContainsString('satisfactoriamente', $notificationData);
    }
}
