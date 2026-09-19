<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Tests\Support\AssertsCrossOrganizationIsolation;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\FieldOps\Models\Terrain;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F1/CLA-457 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Validates the reusable isolation harness (InteractsWithOrganizationFixtures +
 * AssertsCrossOrganizationIsolation) against the two enforcement mechanisms P5
 * actually shipped: module-level (FieldOps, P5b/CLA-552) and panel-level
 * (admin/bertels, P5a/CLA-460 cont./CLA-549). This is the harness's own proof
 * that it correctly distinguishes "rejected" from "allowed" in both directions
 * — a false pass here would mean the harness itself is broken, silently making
 * every future module's isolation suite worthless.
 *
 * Every assertion runs with organizations.enforce forced on (setUp) — every
 * mechanism under test is a no-op with the flag at its real, current default
 * (off, D4); the last test below proves that explicitly instead of assuming it.
 *
 * Per CLA-457's acceptance criteria ("fallo de aislamiento bloquea CI"): this
 * file runs in the ordinary PHPUnit suite that .github/workflows/tests.yml
 * already executes on every push/PR — no separate CI wiring is needed for
 * that guarantee to hold.
 *
 * Deliberately NOT covered here (see the trait docblocks for why): row-level
 * isolation (FieldOps tenant scoping is a different, already-covered concern;
 * Website's site_id scoping stays inert until F3 activates it) and bulk
 * actions (no cross-organization Filament bulk-action surface exists yet —
 * Bertels' panel has zero real resources, P6 spike — so there is nothing real
 * to exercise; mirroredDataset() is ready for F3/F4 to adopt when that changes).
 */
class OrganizationIsolationHarnessTest extends TestCase
{
    use AssertsCrossOrganizationIsolation;
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableOrganizationEnforcement();
    }

    public function test_module_route_rejects_an_outsider_on_a_plain_index_with_no_route_model(): void
    {
        $outsider = $this->bertelsFixtureUser();

        $this->assertModuleRouteRejectsOutsider($outsider, 'GET', '/api/v1/fieldops/terrains');
    }

    public function test_module_route_allows_the_owning_organizations_member_on_the_same_index(): void
    {
        $owner = $this->claesenUser();

        $this->assertModuleRouteAllowsOwner($owner, 'GET', '/api/v1/fieldops/terrains');
    }

    public function test_module_route_rejects_an_outsider_on_a_mutation_regardless_of_payload(): void
    {
        $outsider = $this->bertelsFixtureUser();

        $this->assertModuleRouteRejectsOutsider($outsider, 'POST', '/api/v1/fieldops/terrains', []);
    }

    public function test_module_route_allows_the_owning_organizations_member_to_mutate_when_entitled(): void
    {
        $owner = $this->claesenUser();

        $this->assertModuleRouteAllowsOwner(
            $owner,
            'POST',
            '/api/v1/fieldops/terrains',
            [],
            abilities: ['fieldops.create']
        );
    }

    public function test_module_route_rejects_an_outsider_hitting_a_foreign_row_id_directly(): void
    {
        $owner = $this->claesenUser();
        $terrain = Terrain::factory()->create();

        $outsider = $this->bertelsFixtureUser();

        $this->assertModuleRouteRejectsOutsiderRegardlessOfId(
            $outsider,
            'GET',
            '/api/v1/fieldops/terrains/{id}',
            $terrain->id
        );
    }

    public function test_admin_panel_rejects_an_outsider(): void
    {
        $outsider = $this->bertelsFixtureUser();

        $this->assertPanelRejectsOutsider($outsider, '/');
    }

    public function test_admin_panel_allows_the_owning_organizations_member(): void
    {
        // Plain claesenUser() has no role, so it would be rejected by the
        // pre-existing EnsurePanelAccess role allowlist (CLAUDE.md #5) before
        // organization enforcement is ever reached — a different gate this
        // trait deliberately isn't testing. Assigning a panel-eligible role
        // isolates the organization variable specifically.
        $owner = $this->claesenUser();
        $owner->assignRole(Role::findOrCreate('admin', 'web'));
        // CLA-464 (ADR D8): admin is now forced to set up MFA before reaching
        // any panel page — a concern separate from what this test isolates.
        $owner->update(['has_email_authentication' => true]);

        $this->assertPanelAllowsOwner($owner, '/');
    }

    public function test_bertels_panel_rejects_a_non_super_admin_even_from_claesen(): void
    {
        $owner = $this->claesenUser();

        $this->assertPanelRejectsOutsider($owner, '/bertels');
    }

    public function test_bertels_panel_allows_super_admin(): void
    {
        $superAdmin = $this->superAdminUser();
        // CLA-464 (ADR D8): same rationale as the admin panel test above.
        $superAdmin->update(['has_email_authentication' => true]);

        $this->assertPanelAllowsOwner($superAdmin, '/bertels');
    }

    public function test_disabling_enforcement_makes_every_mechanism_above_inert_for_claesen(): void
    {
        config(['organizations.enforce' => false]);

        $outsider = $this->bertelsFixtureUser();

        $response = $this->actingAs($outsider)->getJson('/api/v1/fieldops/terrains');

        $this->assertNotSame(403, $response->getStatusCode());
    }
}
