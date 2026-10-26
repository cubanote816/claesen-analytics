<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Terrain;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F1/P5c of the multi-organization program (ADR D5) —
 * docs/ai/adr-multi-organization.md.
 *
 * app/Providers/AppServiceProvider.php's Gate::before. Every test defines
 * a throwaway ability that always returns false, so a true result can
 * only come from Gate::before's own short-circuit — this isolates the
 * hook's own logic from any real policy's independent reasoning (several
 * FieldOps policies, for example, grant access via a global Spatie
 * permission unrelated to organization — see the ADR follow-up finding
 * this ticket documents; Gate::before is deliberately NOT a substitute
 * for that).
 *
 * No real non-Claesen user exists (ADR D10) — every test uses a fixture
 * organization created and rolled back inside RefreshDatabase.
 */
final class GateBeforeOrganizationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Gate::define('dummy-ability-for-gate-before-test', fn () => false);
    }

    public function test_with_enforcement_off_super_admin_bypasses_everything_regardless_of_organization(): void
    {
        config(['organizations.enforce' => false]);

        $user = $this->userInFixtureOrganization();
        $terrain = Terrain::factory()->create();

        $this->assertTrue($user->can('dummy-ability-for-gate-before-test', $terrain));
    }

    public function test_with_enforcement_on_a_claesen_super_admin_still_bypasses_a_registered_model(): void
    {
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true]); // defaults to Claesen
        $user->assignRole('super_admin');
        $terrain = Terrain::factory()->create();

        $this->assertTrue($user->can('dummy-ability-for-gate-before-test', $terrain));
    }

    public function test_with_enforcement_on_a_fixture_org_super_admin_is_denied_a_registered_model(): void
    {
        config(['organizations.enforce' => true]);

        $user = $this->userInFixtureOrganization();
        $terrain = Terrain::factory()->create();

        $this->assertFalse($user->can('dummy-ability-for-gate-before-test', $terrain));
    }

    public function test_with_enforcement_on_a_class_string_subject_for_a_registered_model_behaves_the_same_as_an_instance(): void
    {
        config(['organizations.enforce' => true]);

        $claesenUser = User::factory()->create(['is_active' => true]);
        $claesenUser->assignRole('super_admin');
        $fixtureUser = $this->userInFixtureOrganization();

        $this->assertTrue($claesenUser->can('dummy-ability-for-gate-before-test', Terrain::class));
        $this->assertFalse($fixtureUser->can('dummy-ability-for-gate-before-test', Terrain::class));
    }

    public function test_with_enforcement_on_an_unregistered_model_still_bypasses_regardless_of_organization(): void
    {
        config(['organizations.enforce' => true]);

        Gate::define('dummy-ability-for-unregistered-model', fn () => false);

        $user = $this->userInFixtureOrganization();
        $unregisteredSubject = Organization::factory()->create();

        // Organization itself is deliberately NOT in organizations.owned_models
        // — it's platform infrastructure, not a Claesen-owned business model.
        $this->assertTrue($user->can('dummy-ability-for-unregistered-model', $unregisteredSubject));
    }

    public function test_with_enforcement_on_no_subject_and_an_unlisted_ability_defers_instead_of_bypassing(): void
    {
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true]); // Claesen, would bypass with a subject
        $user->assignRole('super_admin');

        $this->assertFalse($user->can('dummy-ability-for-gate-before-test'));
    }

    public function test_a_non_super_admin_is_unaffected_by_any_of_this(): void
    {
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $terrain = Terrain::factory()->create();

        $this->assertFalse($user->can('dummy-ability-for-gate-before-test', $terrain));
    }

    private function userInFixtureOrganization(): User
    {
        $fixtureOrg = Organization::factory()->create(['slug' => 'fixture-non-claesen-org']);

        $user = User::factory()->create([
            'organization_id' => $fixtureOrg->id,
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        return $user->refresh();
    }
}
