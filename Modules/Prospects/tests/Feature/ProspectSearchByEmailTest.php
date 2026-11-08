<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProspectSearchByEmailTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    // The prospects table must be searchable by a location's email, not only by name/
    // region/channel, so operators can find a club from the contact address they have.
    public function test_prospects_table_can_be_searched_by_location_email(): void
    {
        $region = Region::query()->first() ?? Region::create(['name' => 'Antwerpen']);

        $match = Prospect::factory()->create(['region_id' => $region->id, 'is_tester' => false]);
        $match->locations()->first()->update(['email' => 'needle.search@claesen-test.be']);

        $other = Prospect::factory()->create(['region_id' => $region->id, 'is_tester' => false]);
        $other->locations()->first()->update(['email' => 'unrelated.other@claesen-test.be']);

        Livewire::actingAs($this->superAdmin())
            ->test(ManageProspects::class)
            ->searchTable('needle.search@claesen-test.be')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }
}
