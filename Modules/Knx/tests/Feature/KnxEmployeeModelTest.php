<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Models\KnxEmployee;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The people table of the KNX domain (CLA-604 K0).
 *
 * What matters: every human reference in the contract resolves to a row here,
 * the display strings the API returns are *derived* (never stored twice), and a
 * Bertels employee is a person first and an account holder second.
 */
final class KnxEmployeeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_name_matches_the_contract_display_format(): void
    {
        $cases = [
            'Jan Van Dyck' => 'J. Van Dyck',
            'Lien Smet' => 'L. Smet',
            'P. Aerts' => 'P. Aerts',
            'Sven Wouters' => 'S. Wouters',
            // Middle words are kept verbatim: Flemish names break a naive
            // "initial + last word" rule.
            'Jan van den Broeck' => 'J. van den Broeck',
            'Mira Claes' => 'M. Claes',
        ];

        foreach ($cases as $name => $expected) {
            $employee = new KnxEmployee(['name' => $name]);

            $this->assertSame($expected, $employee->shortName(), "for [{$name}]");
        }
    }

    public function test_a_single_word_name_still_produces_a_short_name(): void
    {
        $this->assertSame('C.', (new KnxEmployee(['name' => 'Cher']))->shortName());
    }

    public function test_initials_come_from_given_name_and_surname_only(): void
    {
        $this->assertSame('JV', KnxEmployee::initialsFromName('Jan Van Dyck'));
        $this->assertSame('LS', KnxEmployee::initialsFromName('Lien Smet'));
        $this->assertSame('MC', KnxEmployee::initialsFromName('Mira Claes'));
        // First two words, per the office app's own data — "Jan Van Dyck" is JV,
        // so "Jan van den Wouwer" is JV too and not JW.
        $this->assertSame('JV', KnxEmployee::initialsFromName('Jan van den Wouwer'));
        $this->assertSame('C', KnxEmployee::initialsFromName('Cher'));
    }

    public function test_office_and_field_are_separated_by_scope(): void
    {
        KnxEmployee::factory()->office()->create(['name' => 'Lien Smet']);
        KnxEmployee::factory()->field()->create(['name' => 'Jan Van Dyck']);

        $this->assertSame(['Lien Smet'], KnxEmployee::query()->office()->pluck('name')->all());
        $this->assertSame(['Jan Van Dyck'], KnxEmployee::query()->field()->pluck('name')->all());
    }

    public function test_inactive_people_are_excluded_by_the_active_scope(): void
    {
        KnxEmployee::factory()->field()->create(['name' => 'Activa']);
        KnxEmployee::factory()->field()->inactive()->create(['name' => 'Ex']);

        $this->assertSame(['Activa'], KnxEmployee::query()->active()->pluck('name')->all());
        $this->assertFalse(KnxEmployee::query()->where('name', 'Ex')->sole()->active);
    }

    public function test_a_person_belongs_to_the_tenant_and_may_have_an_account(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        $user = User::factory()->create();

        $withAccount = KnxEmployee::factory()->office()->forOrganization($organization)->create([
            'user_id' => $user->id,
        ]);
        $withoutAccount = KnxEmployee::factory()->field()->forOrganization($organization)->create();

        $this->assertSame($organization->id, $withAccount->organization->id);
        $this->assertSame($user->id, $withAccount->user->id);
        $this->assertNull($withoutAccount->user);
        $this->assertTrue($withAccount->hasKantoorAccess());
    }

    public function test_kantoor_access_needs_an_office_person_that_is_active_and_has_an_account(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        // One account belongs to exactly one person (unique on user_id), so each
        // case gets its own user.
        $this->assertFalse(
            KnxEmployee::factory()->field()->forOrganization($organization)->create(['user_id' => User::factory()])->hasKantoorAccess(),
            'a field technician is not an office user',
        );
        $this->assertFalse(
            KnxEmployee::factory()->office()->forOrganization($organization)->create()->hasKantoorAccess(),
            'without an account there is nobody to sign in',
        );
        $this->assertFalse(
            KnxEmployee::factory()->office()->inactive()->forOrganization($organization)->create(['user_id' => User::factory()])->hasKantoorAccess(),
            'an inactive person must not get in',
        );
    }

    public function test_the_knx_app_roles_exist_and_grant_no_filament_panel_access(): void
    {
        foreach (['knx_office', 'knx_field'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('knx_office');

        // The guard that matters: Kantoor/Veld are their own apps, so these
        // roles are deliberately absent from User::hasPanelAccess().
        $this->assertFalse($user->hasPanelAccess());
    }
}
