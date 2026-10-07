<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Filament\Resources\Users\Pages\ListUsers;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-599: the backoffice shows each user's organization (column, filter) and
 * never lets the general edit form change it.
 */
final class UserOrganizationUiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $bertels;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->bertels = Organization::factory()->create(['slug' => 'electro-bertels', 'name' => 'Electro Bertels']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin);
    }

    public function test_the_list_shows_each_users_organization_and_filters_by_it(): void
    {
        $claesenUser = User::factory()->create();
        $bertelsUser = User::factory()->create(['organization_id' => $this->bertels->id]);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$claesenUser, $bertelsUser])
            ->assertTableColumnStateSet('organization.name', 'Electro Bertels', $bertelsUser)
            ->assertTableColumnStateSet('organization.name', Organization::query()->find(Organization::claesenId())->name, $claesenUser)
            ->filterTable('organization_id', $this->bertels->id)
            ->assertCanSeeTableRecords([$bertelsUser])
            ->assertCanNotSeeTableRecords([$claesenUser]);
    }

    public function test_the_edit_form_shows_the_organization_read_only_and_does_not_change_it(): void
    {
        $bertelsUser = User::factory()->create(['organization_id' => $this->bertels->id]);

        Livewire::test(EditUser::class, ['record' => $bertelsUser->getKey()])
            ->assertSee('Electro Bertels')
            ->fillForm(['name' => 'Renamed Person'])
            ->call('save')
            ->assertHasNoFormErrors();

        $bertelsUser->refresh();
        $this->assertSame('Renamed Person', $bertelsUser->name);
        $this->assertSame($this->bertels->id, $bertelsUser->organization_id);
    }
}
