<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F1/P5a of the multi-organization program (CLA-460 cont.) —
 * docs/ai/adr-multi-organization.md.
 *
 * The first enforcement layer the ADR names: "paneles y logins". Gated by
 * config('organizations.enforce') — off (the default everywhere in
 * production today) it changes nothing observable for Claesen; on, a user
 * outside Claesen's organization is rejected from the admin panel.
 *
 * No real non-Claesen user exists (ADR D10, "regla de hierro") — every test
 * here uses a fixture organization created and rolled back within
 * RefreshDatabase's transaction, never persisted for real.
 */
final class PanelOrganizationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authorization, not the asset pipeline — same rationale as
        // PanelAccessMatrixTest::setUp().
        $this->withoutVite();

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_enforcement_is_off_by_default(): void
    {
        $this->assertFalse(config('organizations.enforce'));
    }

    public function test_with_enforcement_off_a_user_outside_claesens_organization_can_still_use_the_admin_panel(): void
    {
        config(['organizations.enforce' => false]);

        $user = $this->userInFixtureOrganization();

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_with_enforcement_on_a_user_outside_claesens_organization_is_rejected_from_the_admin_panel(): void
    {
        config(['organizations.enforce' => true]);

        $user = $this->userInFixtureOrganization();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_with_enforcement_on_a_claesen_user_is_completely_unaffected(): void
    {
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true]); // defaults to Claesen's org
        $user->assignRole('admin');

        $this->assertSame(Organization::claesenId(), $user->organization_id);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_with_enforcement_on_the_bertels_branch_is_unaffected(): void
    {
        // super_admin, Claesen org (default) — still reaches bertels
        // regardless of the flag, same as before this change.
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('bertels')));
    }

    public function test_a_manipulated_url_to_the_admin_panel_returns_403_for_another_organization_with_enforcement_on(): void
    {
        config(['organizations.enforce' => true]);

        $user = $this->userInFixtureOrganization();

        $this->actingAs($user)->get('/')->assertForbidden();
    }

    public function test_a_manipulated_url_to_the_admin_panel_still_succeeds_for_another_organization_with_enforcement_off(): void
    {
        config(['organizations.enforce' => false]);

        $user = $this->userInFixtureOrganization();

        $this->actingAs($user)->get('/')->assertSuccessful();
    }

    private function userInFixtureOrganization(): User
    {
        $fixtureOrg = Organization::factory()->create(['slug' => 'fixture-non-claesen-org']);

        $user = User::factory()->create([
            'organization_id' => $fixtureOrg->id,
            'is_active' => true,
            'password_set_at' => now(),
        ]);
        $user->assignRole('admin');

        return $user->refresh();
    }
}
