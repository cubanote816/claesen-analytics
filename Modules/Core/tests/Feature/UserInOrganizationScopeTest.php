<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F1/P5d of the multi-organization program (ADR §6, "asíncrono y
 * destinatarios") — docs/ai/adr-multi-organization.md.
 *
 * User::scopeInOrganization() is the fix for the CRITICAL gap the P0
 * baseline (AccessControlContractTest::operationalRecipientQueries) froze
 * across 7 recipient-selection sites in FieldOps/Safety/Mailing: every one
 * of them picked recipients by global Spatie role alone, with zero
 * organization filter.
 *
 * Gated by config('organizations.enforce') (D4) — a no-op with the flag
 * off (the default in every environment today). No real non-Claesen user
 * exists (ADR D10) — every test here uses a fixture organization created
 * and rolled back inside RefreshDatabase.
 */
final class UserInOrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_enforcement_off_the_scope_is_a_no_op(): void
    {
        config(['organizations.enforce' => false]);

        $claesenUser = User::factory()->create();
        $fixtureUser = $this->userInFixtureOrganization();

        $ids = User::query()->inOrganization()->pluck('id');

        $this->assertTrue($ids->contains($claesenUser->id));
        $this->assertTrue($ids->contains($fixtureUser->id));
    }

    public function test_with_enforcement_on_it_defaults_to_claesen(): void
    {
        config(['organizations.enforce' => true]);

        $claesenUser = User::factory()->create();
        $fixtureUser = $this->userInFixtureOrganization();

        $ids = User::query()->inOrganization()->pluck('id');

        $this->assertTrue($ids->contains($claesenUser->id));
        $this->assertFalse($ids->contains($fixtureUser->id));
    }

    public function test_with_enforcement_on_an_explicit_organization_id_overrides_the_claesen_default(): void
    {
        config(['organizations.enforce' => true]);

        $claesenUser = User::factory()->create();
        $fixtureUser = $this->userInFixtureOrganization();

        $ids = User::query()->inOrganization($fixtureUser->organization_id)->pluck('id');

        $this->assertFalse($ids->contains($claesenUser->id));
        $this->assertTrue($ids->contains($fixtureUser->id));
    }

    public function test_it_composes_with_a_preceding_role_scope(): void
    {
        config(['organizations.enforce' => true]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $claesenAdmin = User::factory()->create();
        $claesenAdmin->assignRole('super_admin');

        $fixtureAdmin = $this->userInFixtureOrganization();
        $fixtureAdmin->assignRole('super_admin');

        $recipients = User::role('super_admin')->inOrganization()->get();

        $this->assertTrue($recipients->contains($claesenAdmin));
        $this->assertFalse($recipients->contains($fixtureAdmin));
    }

    private function userInFixtureOrganization(): User
    {
        $fixtureOrg = Organization::factory()->create(['slug' => 'fixture-non-claesen-org']);

        return User::factory()->create(['organization_id' => $fixtureOrg->id]);
    }
}
