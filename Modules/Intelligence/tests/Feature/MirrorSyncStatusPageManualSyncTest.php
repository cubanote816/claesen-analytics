<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Intelligence\Filament\Pages\MirrorSyncStatusPage;
use Modules\Intelligence\Jobs\RunManualDataSyncJob;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MirrorSyncStatusPageManualSyncTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        return $user;
    }

    #[DataProvider('manualSyncActionProvider')]
    public function test_each_header_action_dispatches_the_matching_task(string $action, string $task): void
    {
        Bus::fake();
        $user = $this->actingAsSuperAdmin();

        Livewire::test(MirrorSyncStatusPage::class)->callAction($action);

        Bus::assertDispatched(RunManualDataSyncJob::class, fn (RunManualDataSyncJob $job) => $job->task === $task && $job->triggeredByUserId === $user->id);
    }

    public static function manualSyncActionProvider(): array
    {
        return [
            'employees' => ['sync_employees', 'employees'],
            'clients' => ['sync_clients', 'clients'],
            'complexes' => ['sync_complexes', 'complexes'],
            'all' => ['sync_all_manual', 'all'],
        ];
    }

    public function test_it_does_not_dispatch_when_a_manual_sync_is_already_running(): void
    {
        Bus::fake();
        $this->actingAsSuperAdmin();
        Cache::put(RunManualDataSyncJob::RUNNING_FLAG_KEY, true, 600);

        Livewire::test(MirrorSyncStatusPage::class)->call('runManualSync', 'clients');

        Bus::assertNotDispatched(RunManualDataSyncJob::class);
    }

    public function test_actions_are_disabled_while_a_manual_sync_is_running(): void
    {
        $this->actingAsSuperAdmin();
        Cache::put(RunManualDataSyncJob::RUNNING_FLAG_KEY, true, 600);

        $component = Livewire::test(MirrorSyncStatusPage::class);

        foreach (['sync_employees', 'sync_clients', 'sync_complexes', 'sync_all_manual'] as $action) {
            $component->assertActionDisabled($action);
        }
    }
}
