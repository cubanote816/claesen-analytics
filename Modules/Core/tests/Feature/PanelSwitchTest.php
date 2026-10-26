<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F1/P4+P6 of the multi-organization program (CLA-460 cont.) —
 * docs/ai/adr-multi-organization.md.
 *
 * The "selector auditado de super_admin" P6's exit criterion names: an
 * explicit, logged action to switch between panels. Deliberately outside any
 * Filament panel — see SwitchPanelController's docblock.
 */
final class PanelSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ROLES = [
        'super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer',
        'project_manager', 'technician', 'client',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ALL_ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_switching_to_bertels_is_redirected_and_logged(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)
            ->get('/switch-panel/bertels?from=admin')
            ->assertRedirect('/bertels');

        $this->assertSame(1, Activity::query()->where('event', 'panel_switched')->count());

        $activity = Activity::query()->where('event', 'panel_switched')->sole();
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame('admin', $activity->properties['from_panel']);
        $this->assertSame('bertels', $activity->properties['to_panel']);
        $this->assertNull($activity->subject_id);
    }

    public function test_super_admin_switching_back_to_admin_is_redirected_and_logged(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)
            ->get('/switch-panel/admin?from=bertels')
            ->assertRedirect('/');

        $activity = Activity::query()->where('event', 'panel_switched')->sole();
        $this->assertSame('bertels', $activity->properties['from_panel']);
        $this->assertSame('admin', $activity->properties['to_panel']);
    }

    public function test_a_missing_or_untrusted_from_query_param_is_recorded_as_unknown(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)->get('/switch-panel/bertels');

        $activity = Activity::query()->where('event', 'panel_switched')->sole();
        $this->assertSame('unknown', $activity->properties['from_panel']);
    }

    public function test_no_other_role_can_switch_panels_and_nothing_is_logged(): void
    {
        foreach (array_diff(self::ALL_ROLES, ['super_admin']) as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/switch-panel/bertels')
                ->assertForbidden();
        }

        $this->actingAs($this->userWithRole(null))
            ->get('/switch-panel/bertels')
            ->assertForbidden();

        $this->assertSame(0, Activity::query()->where('event', 'panel_switched')->count());
    }

    public function test_an_unknown_panel_identifier_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/switch-panel/not-a-real-panel')
            ->assertNotFound();

        $this->assertSame(0, Activity::query()->where('event', 'panel_switched')->count());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/switch-panel/bertels')->assertRedirect(route('filament.admin.auth.login'));
    }

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->refresh();
    }
}
