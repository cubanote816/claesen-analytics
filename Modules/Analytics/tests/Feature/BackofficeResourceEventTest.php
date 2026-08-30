<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Permissions\Pages\CreatePermission;
use Modules\Core\Filament\Resources\Permissions\Pages\EditPermission;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackofficeResourceEventTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_creating_a_resource_via_the_panel_records_resource_created(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreatePermission::class)
            ->fillForm(['name' => 'analytics.test-permission', 'guard_name' => 'web'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('app_events', [
            'event_name' => 'resource_created',
            'app' => 'backoffice',
            'user_id' => $user->id,
            'entity_type' => 'Permission',
        ]);
    }

    public function test_updating_a_resource_via_the_panel_records_resource_updated(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $permission = Permission::create(['name' => 'analytics.existing', 'guard_name' => 'web']);

        Livewire::test(EditPermission::class, ['record' => $permission->getKey()])
            ->fillForm(['name' => 'analytics.renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('app_events', [
            'event_name' => 'resource_updated',
            'app' => 'backoffice',
            'user_id' => $user->id,
            'entity_type' => 'Permission',
        ]);
    }

    public function test_mutation_outside_the_panel_does_not_record_an_event(): void
    {
        // No Filament::setCurrentPanel() here — same as a sync command or an
        // API request, which never runs Filament's SetUpPanel middleware.
        Permission::create(['name' => 'analytics.synced-not-tracked', 'guard_name' => 'web']);

        $this->assertDatabaseMissing('app_events', [
            'entity_type' => 'Permission',
        ]);
    }
}
