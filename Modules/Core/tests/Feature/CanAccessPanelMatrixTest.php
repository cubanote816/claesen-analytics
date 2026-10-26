<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may enter which panel — CLA-611, and the amendment to ADR D10.
 *
 * The decision was to open the `bertels` panel to the client's own people so they
 * can approve their pages. The dangerous half is not that door: it is the other
 * one. Before this, the only thing between a Bertels user and Claesen's data was
 * that no Bertels user existed yet, because the admin panel admits any active user
 * and the guard that would have stopped it sat behind a flag that is off.
 *
 * So this matrix is the point of the change, and every row is a boundary:
 *
 *   Bertels user  -> bertels yes, admin NO
 *   Claesen user  -> admin yes,   bertels no
 *   super_admin   -> both (as before, even with a null organization: that is how
 *                    the seeded accounts look)
 *   null org      -> admin yes (declared residual), bertels NO
 *   inactive      -> neither
 */
final class CanAccessPanelMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    /** The organization the bertels panel belongs to, with its own site. */
    private function bertelsOrganization(): Organization
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        Site::factory()->create([
            'organization_id' => $organization->id,
            'key' => 'electro-bertels',
            'status' => Site::STATUS_ACTIVE,
        ]);

        return $organization;
    }

    private function userIn(?Organization $organization, bool $active = true): User
    {
        return User::factory()->create([
            'organization_id' => $organization?->id,
            'is_active' => $active,
        ]);
    }

    private function canReach(User $user, string $panelId): bool
    {
        return $user->canAccessPanel(Filament::getPanel($panelId));
    }

    public function test_a_bertels_user_reaches_its_own_panel_and_not_claesen_s(): void
    {
        $bertels = $this->userIn($this->bertelsOrganization());

        $this->assertTrue($this->canReach($bertels, 'bertels'));
        // The whole reason this test exists: that door must never open.
        $this->assertFalse($this->canReach($bertels, 'admin'));
    }

    public function test_the_admin_boundary_does_not_depend_on_the_enforcement_flag(): void
    {
        config()->set('organizations.enforce', false);

        $bertels = $this->userIn($this->bertelsOrganization());

        $this->assertFalse($this->canReach($bertels, 'admin'));

        config()->set('organizations.enforce', true);

        $this->assertFalse($this->canReach($bertels, 'admin'));
    }

    public function test_a_claesen_user_still_reaches_admin_and_not_bertels(): void
    {
        $claesen = $this->userIn(Organization::query()->find(Organization::claesenId()));

        $this->assertTrue($this->canReach($claesen, 'admin'));
        $this->assertFalse($this->canReach($claesen, 'bertels'));
    }

    public function test_a_super_admin_without_an_organization_reaches_both_as_before(): void
    {
        // The seeded accounts have a null organization (DatabaseSeeder): whatever
        // this rule becomes, it must not lock the existing team out.
        $superAdmin = $this->userIn(null);
        $superAdmin->assignRole('super_admin');

        $this->assertTrue($this->canReach($superAdmin, 'bertels'));
        $this->assertTrue($this->canReach($superAdmin, 'admin'));
    }

    public function test_a_user_without_an_organization_cannot_reach_the_bertels_panel(): void
    {
        // Fail-closed: belonging comes from the panel's own site, so a user with
        // no organization belongs to nothing.
        $this->assertFalse($this->canReach($this->userIn(null), 'bertels'));
    }

    public function test_an_inactive_user_reaches_neither(): void
    {
        $inactive = $this->userIn($this->bertelsOrganization(), active: false);

        $this->assertFalse($this->canReach($inactive, 'bertels'));
        $this->assertFalse($this->canReach($inactive, 'admin'));
    }

    public function test_a_bertels_user_is_not_admitted_when_the_panel_has_no_site(): void
    {
        // No site registered for the panel → nobody belongs to it but super_admin.
        $orphan = $this->userIn(Organization::factory()->create(['slug' => 'sin-sitio']));

        $this->assertFalse($this->canReach($orphan, 'bertels'));
    }
}
