<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Prospects\Filament\Pages\SyncDashboardPage;
use Modules\Prospects\Jobs\ExecuteSyncJob;
use Modules\Prospects\Jobs\MasterSyncJob;
use Modules\Prospects\Models\SyncHistory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncDashboardGuardTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        return $user;
    }

    private function regularUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    private function syncHistory(array $attrs = []): SyncHistory
    {
        return SyncHistory::create(array_merge([
            'command'       => 'prospects:sync-lbfa-clubs',
            'type'          => 'individual',
            'status'        => 'pending',
            'records_count' => 0,
            'logs'          => [],
            'started_at'    => null,
            'finished_at'   => null,
            'user_id'       => null,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // canAccess — permissions
    // -------------------------------------------------------------------------

    public function test_super_admin_can_access_sync_dashboard(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertTrue(SyncDashboardPage::canAccess());
    }

    public function test_non_super_admin_cannot_access_sync_dashboard(): void
    {
        $this->actingAs($this->regularUser());

        $this->assertFalse(SyncDashboardPage::canAccess());
    }

    public function test_unauthenticated_cannot_access_sync_dashboard(): void
    {
        $this->assertFalse(SyncDashboardPage::canAccess());
    }

    // -------------------------------------------------------------------------
    // syncFederation — guard: master active
    // -------------------------------------------------------------------------

    public function test_sync_federation_blocked_when_master_is_pending(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory([
            'command' => 'prospects:sync-master',
            'type'    => 'master',
            'status'  => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'prospects:sync-lbfa-clubs');

        Queue::assertNotPushed(ExecuteSyncJob::class);
        $this->assertDatabaseCount('prospects_sync_histories', 1);
    }

    public function test_sync_federation_blocked_when_master_is_running(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory([
            'command'    => 'prospects:sync-master',
            'type'       => 'master',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'prospects:sync-lbfa-clubs');

        Queue::assertNotPushed(ExecuteSyncJob::class);
        $this->assertDatabaseCount('prospects_sync_histories', 1);
    }

    // -------------------------------------------------------------------------
    // syncFederation — guard: duplicate command
    // -------------------------------------------------------------------------

    public function test_sync_federation_blocked_on_duplicate_pending(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory(['command' => 'prospects:sync-lbfa-clubs', 'status' => 'pending']);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'prospects:sync-lbfa-clubs');

        Queue::assertNotPushed(ExecuteSyncJob::class);
        $this->assertDatabaseCount('prospects_sync_histories', 1);
    }

    public function test_sync_federation_blocked_on_duplicate_running(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory(['command' => 'prospects:sync-lbfa-clubs', 'status' => 'running', 'started_at' => now()]);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'prospects:sync-lbfa-clubs');

        Queue::assertNotPushed(ExecuteSyncJob::class);
        $this->assertDatabaseCount('prospects_sync_histories', 1);
    }

    // -------------------------------------------------------------------------
    // syncFederation — invalid command
    // -------------------------------------------------------------------------

    public function test_sync_federation_rejects_unknown_command(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'system:drop-tables');

        Queue::assertNotPushed(ExecuteSyncJob::class);
        $this->assertDatabaseCount('prospects_sync_histories', 0);
    }

    // -------------------------------------------------------------------------
    // syncFederation — success path
    // -------------------------------------------------------------------------

    public function test_sync_federation_creates_history_and_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'prospects:sync-lbfa-clubs');

        Queue::assertPushed(ExecuteSyncJob::class);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'command' => 'prospects:sync-lbfa-clubs',
            'type'    => 'individual',
            'status'  => 'pending',
            'user_id' => $user->id,
        ]);
    }

    public function test_sync_federation_different_commands_can_run_concurrently(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        // LBFA already running
        $this->syncHistory(['command' => 'prospects:sync-lbfa-clubs', 'status' => 'running', 'started_at' => now()]);

        // AFT should be allowed — different command, no master active
        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncFederation', 'prospects:sync-aft-clubs');

        Queue::assertPushed(ExecuteSyncJob::class);
        $this->assertDatabaseHas('prospects_sync_histories', [
            'command' => 'prospects:sync-aft-clubs',
            'status'  => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // syncAll — guard: any active
    // -------------------------------------------------------------------------

    public function test_sync_all_blocked_when_any_individual_is_pending(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory(['status' => 'pending']);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncAll');

        Queue::assertNotPushed(MasterSyncJob::class);
        $this->assertDatabaseMissing('prospects_sync_histories', [
            'command' => 'prospects:sync-master',
        ]);
    }

    public function test_sync_all_blocked_when_any_individual_is_running(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory(['status' => 'running', 'started_at' => now()]);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncAll');

        Queue::assertNotPushed(MasterSyncJob::class);
        $this->assertDatabaseMissing('prospects_sync_histories', [
            'command' => 'prospects:sync-master',
        ]);
    }

    public function test_sync_all_blocked_when_master_already_running(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        $this->syncHistory([
            'command'    => 'prospects:sync-master',
            'type'       => 'master',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncAll');

        Queue::assertNotPushed(MasterSyncJob::class);
        $this->assertDatabaseCount('prospects_sync_histories', 1);
    }

    // -------------------------------------------------------------------------
    // syncAll — success path
    // -------------------------------------------------------------------------

    public function test_sync_all_creates_master_history_and_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->superAdmin();

        Livewire::actingAs($user)
            ->test(SyncDashboardPage::class)
            ->call('syncAll');

        Queue::assertPushed(MasterSyncJob::class);

        $this->assertDatabaseHas('prospects_sync_histories', [
            'command' => 'prospects:sync-master',
            'type'    => 'master',
            'status'  => 'pending',
            'user_id' => $user->id,
        ]);
    }
}
