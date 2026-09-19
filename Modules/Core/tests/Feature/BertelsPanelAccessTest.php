<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F1/P6 spike (CLA-549) — docs/ai/adr-multi-organization.md.
 *
 * The Bertels panel is reachable by super_admin only, via
 * User::canAccessPanel() rather than EnsurePanelAccess (Claesen's role
 * allowlist, deliberately not applied here). No real Bertels user exists yet
 * (ADR D10) — this is exclusively for the team to verify the panel exists.
 */
final class BertelsPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ROLES = [
        'super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer',
        'project_manager', 'technician', 'client',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Authorization, not the asset pipeline — same rationale as
        // PanelAccessMatrixTest::setUp().
        $this->withoutVite();

        foreach (self::ALL_ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_can_reach_the_bertels_dashboard(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/bertels')
            ->assertSuccessful();
    }

    public function test_no_other_role_can_reach_the_bertels_dashboard(): void
    {
        foreach (array_diff(self::ALL_ROLES, ['super_admin']) as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/bertels')
                ->assertForbidden();
        }

        // A user with no role at all is also out.
        $this->actingAs($this->userWithRole(null))
            ->get('/bertels')
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_the_bertels_login_not_claesens(): void
    {
        $this->get('/bertels')->assertRedirect('/bertels/login');
    }

    public function test_an_inactive_super_admin_cannot_use_the_bertels_panel(): void
    {
        $user = $this->userWithRole('super_admin');
        $user->update(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('bertels')));
    }

    // Deliberately 2 separate test methods rather than one method calling
    // ->get('/') then ->get('/bertels'): Filament\Navigation\NavigationManager
    // is bound `scoped` (vendor/filament/filament/.../FilamentServiceProvider.php),
    // and Laravel only flushes `scoped` bindings on queue-job boundaries
    // (Illuminate\Queue\QueueServiceProvider — the exact mechanism ADR D6
    // documents for OrganizationContext), never after a plain HTTP request. In
    // real production (PHP-FPM, no Octane) each request is a fresh process, so
    // this never leaks — but PHPUnit's test client reuses one container across
    // sequential ->get() calls in the same method, so the second call would
    // still see the first panel's already-resolved navigation groups and 500.
    // Two methods each get a fresh container via TestCase::setUp(), which is
    // what an actual browser switching panels experiences.
    public function test_super_admin_reaches_claesens_panel_unaffected_by_bertels(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/')
            ->assertSuccessful();
    }

    public function test_admin_reaches_claesens_panel(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/')
            ->assertSuccessful();
    }

    public function test_admin_does_not_reach_bertels(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/bertels')
            ->assertForbidden();
    }

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
            // CLA-464 (ADR D8): super_admin/admin are now forced to set up MFA
            // before reaching any dashboard — a concern separate from the
            // panel-access matrix this file tests. See PanelAccessMatrixTest
            // for the same rationale.
            'has_email_authentication' => true,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->refresh();
    }
}
