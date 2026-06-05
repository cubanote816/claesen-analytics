<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProspectTabSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        return $user;
    }

    // When the user switches tabs, any records selected in the previous tab must be
    // deselected. Otherwise BulkActions run against a tab-filtered query that excludes
    // the stale IDs, producing 0 matches and a misleading "no prospects selected" error.

    public function test_switching_tab_dispatches_deselect_all_records_event(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ManageProspects::class)
            ->set('activeTab', 'testers')
            ->assertDispatched('deselectAllTableRecords');
    }

    public function test_switching_from_testers_to_real_prospects_dispatches_deselect(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ManageProspects::class)
            ->set('activeTab', 'testers')
            ->assertDispatched('deselectAllTableRecords')
            ->set('activeTab', 'real_prospects')
            ->assertDispatched('deselectAllTableRecords');
    }

    public function test_switching_to_all_tab_dispatches_deselect(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ManageProspects::class)
            ->set('activeTab', 'all')
            ->assertDispatched('deselectAllTableRecords');
    }
}
