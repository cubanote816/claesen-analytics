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

class ProspectLocationEmailUniqueTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    // The prospects_locations.email column is uniquely indexed. Editing a prospect and
    // saving a location with an email that already exists on another location must be
    // caught as a form validation error, not blow up as a DB UniqueConstraintViolation
    // (HTTP 500). This asserts the edit action rejects the duplicate without writing it.
    public function test_editing_a_prospect_with_a_duplicate_location_email_is_rejected(): void
    {
        $region = Region::query()->first() ?? Region::create(['name' => 'Antwerpen']);

        // Prospect A owns the email we will try to steal.
        $prospectA = Prospect::factory()->create(['region_id' => $region->id]);
        $takenEmail = $prospectA->locations()->first()->email;

        // Prospect B is the one we edit, reusing A's email on its own location.
        $prospectB = Prospect::factory()->create([
            'region_id'  => $region->id,
            'federation' => 'RBFA',
            'language'   => 'nl',
        ]);
        $locationB = $prospectB->locations()->first();

        Livewire::actingAs($this->superAdmin())
            ->test(ManageProspects::class)
            ->callTableAction('edit', $prospectB, data: [
                'name'       => $prospectB->name,
                'region_id'  => $region->id,
                'federation' => 'RBFA',
                'language'   => 'nl',
                'locations'  => [
                    $locationB->getKey() => [
                        'contact_type' => 'headquarters',
                        'contact_name' => $locationB->contact_name,
                        'email'        => $takenEmail,
                        'phone'        => $locationB->phone,
                        'address'      => $locationB->address,
                    ],
                ],
            ])
            ->assertHasTableActionErrors();

        // The duplicate was never written: the email still belongs to exactly one location.
        $this->assertSame(1, ProspectLocation::query()->where('email', $takenEmail)->count());
    }
}
