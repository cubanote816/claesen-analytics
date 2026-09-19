<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Services\FieldOpsTenantService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-558 (F2, follow-up to CLA-557/P5c) — docs/ai/adr-multi-organization.md,
 * decisions D2 and D5.
 *
 * FieldOpsTenantService::hasBroadAccess() used to be a bare Spatie permission
 * check (`$user->can('fieldops.view-all-clients')`). Spatie roles/permissions
 * stay global on purpose (D2 — "the organization is the boundary, the role is
 * the capability inside it"), so ANY role carrying that permission
 * (super_admin, admin, financial_manager, hr_manager, viewer, or
 * project_manager per CLA-377) granted unrestricted FieldOps read/write to a
 * user regardless of organization — independent of, and unprotected by,
 * Gate::before (D5, CLA-557), which only ever intercepts BEFORE a policy
 * runs and does nothing to fix what the policy itself decides once deferred.
 *
 * These tests exercise FieldOpsTenantService directly (not via HTTP), the
 * same way GateBeforeOrganizationEnforcementTest isolates Gate::before from
 * any one policy's own reasoning — this is a second, independent layer
 * (D4's "defense in depth", not a replacement for RequireOrganization/P5b or
 * canAccessPanel/P5a, both of which already happen to block every practical
 * route into FieldOps for a non-Claesen user today).
 *
 * No real non-Claesen user exists (ADR D10) — every test uses a fixture
 * organization via InteractsWithOrganizationFixtures (CLA-457).
 */
final class FieldOpsTenantServiceOrganizationEnforcementTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_with_enforcement_off_a_fixture_org_user_with_the_permission_still_gets_broad_access(): void
    {
        $user = $this->broadAccessUserInFixtureOrganization();

        $this->assertTrue(app(FieldOpsTenantService::class)->hasBroadAccess($user));
    }

    public function test_with_enforcement_on_a_claesen_user_with_the_permission_keeps_broad_access(): void
    {
        $this->enableOrganizationEnforcement();

        $user = $this->claesenUser();
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        $this->assertTrue(app(FieldOpsTenantService::class)->hasBroadAccess($user));
    }

    public function test_with_enforcement_on_a_fixture_org_user_with_the_permission_loses_broad_access(): void
    {
        $this->enableOrganizationEnforcement();

        $user = $this->broadAccessUserInFixtureOrganization();

        $this->assertFalse(app(FieldOpsTenantService::class)->hasBroadAccess($user));
    }

    public function test_with_enforcement_on_a_fixture_org_super_admin_also_loses_broad_access(): void
    {
        // Distinct from Gate::before's super_admin bypass (D5, CLA-557):
        // Gate::before only short-circuits a model registered in
        // organizations.owned_models when the ability check reaches it
        // directly. A policy that defers to FieldOpsTenantService::canView()
        // internally (as FieldOpsInfrastructurePolicy does) never re-consults
        // Gate::before — this proves the service's own check holds even for
        // the role that would otherwise bypass everything.
        $this->enableOrganizationEnforcement();

        $user = $this->superAdminUser($this->bertelsFixtureOrganization());
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        $this->assertFalse(app(FieldOpsTenantService::class)->hasBroadAccess($user));
    }

    public function test_with_enforcement_on_a_fixture_org_user_without_broad_access_falls_back_to_scoped_and_sees_nothing(): void
    {
        $this->enableOrganizationEnforcement();

        Complex::factory()->create();

        $user = $this->broadAccessUserInFixtureOrganization();
        $tenants = app(FieldOpsTenantService::class);

        $this->assertTrue($tenants->allowedClientIds($user)->isEmpty());
        $this->assertTrue($tenants->scopeForUser(Complex::query(), $user, Complex::class)->doesntExist());
    }

    public function test_with_enforcement_on_a_fixture_org_user_cannot_view_a_specific_claesen_complex(): void
    {
        $this->enableOrganizationEnforcement();

        $complex = Complex::factory()->create();
        $user = $this->broadAccessUserInFixtureOrganization();

        $this->assertFalse(app(FieldOpsTenantService::class)->canView($user, $complex));
    }

    public function test_with_enforcement_on_a_claesen_user_still_views_every_claesen_complex(): void
    {
        $this->enableOrganizationEnforcement();

        $complex = Complex::factory()->create();
        $user = $this->claesenUser();
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        $this->assertTrue(app(FieldOpsTenantService::class)->canView($user, $complex));
    }

    private function broadAccessUserInFixtureOrganization(): User
    {
        $user = $this->bertelsFixtureUser();
        $user->assignRole('admin');
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        return $user->refresh();
    }
}
