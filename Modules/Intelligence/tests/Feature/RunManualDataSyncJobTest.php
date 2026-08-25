<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\User;
use Modules\Intelligence\Jobs\RunManualDataSyncJob;
use Modules\Intelligence\Services\ManualDataSyncService;
use Tests\TestCase;

/**
 * CLA-439 — el bug real encontrado durante la verificación manual (dos syncs
 * de clientes corriendo en paralelo → UniqueConstraintViolationException en
 * fo_clients.relation_id) es exactamente lo que este lock previene.
 */
class RunManualDataSyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(RunManualDataSyncJob::RUNNING_FLAG_KEY);
    }

    public function test_it_runs_the_requested_task_and_notifies_the_triggering_user_on_success(): void
    {
        $user = User::factory()->create();

        $service = \Mockery::mock(ManualDataSyncService::class);
        $service->shouldReceive('syncClients')->once();

        (new RunManualDataSyncJob('clients', $user->id))->handle($service);

        $this->assertFalse(Cache::has(RunManualDataSyncJob::RUNNING_FLAG_KEY));

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('success', $notification->data['status'] ?? null);
    }

    public function test_it_notifies_failure_and_still_releases_the_lock_when_the_service_throws(): void
    {
        $user = User::factory()->create();

        $service = \Mockery::mock(ManualDataSyncService::class);
        $service->shouldReceive('syncComplexes')->once()->andThrow(new \RuntimeException('boom'));

        (new RunManualDataSyncJob('complexes', $user->id))->handle($service);

        $this->assertFalse(Cache::has(RunManualDataSyncJob::RUNNING_FLAG_KEY));

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('danger', $notification->data['status'] ?? null);
    }

    public function test_it_does_nothing_when_another_sync_already_holds_the_lock(): void
    {
        $user = User::factory()->create();
        $lock = Cache::lock('fieldops-manual-sync-lock', 600);
        $this->assertTrue($lock->get());

        $service = \Mockery::mock(ManualDataSyncService::class);
        $service->shouldNotReceive('syncEmployees');

        (new RunManualDataSyncJob('employees', $user->id))->handle($service);

        $this->assertDatabaseCount('notifications', 0);

        $lock->release();
    }

    public function test_it_skips_notification_when_no_triggering_user_is_recorded(): void
    {
        $service = \Mockery::mock(ManualDataSyncService::class);
        $service->shouldReceive('syncAll')->once();

        (new RunManualDataSyncJob('all', null))->handle($service);

        $this->assertDatabaseCount('notifications', 0);
    }
}
