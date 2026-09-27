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
 * CLA-602 (Fase 1 de la app KNX de Electro Bertels): punto de entrada desde
 * el menú del panel Bertels. Mismo control de acceso que el panel en sí
 * (User::canAccessPanel('bertels'), ADR D10) — ver BertelsPanelAccessTest
 * para el precedente de este patrón de prueba.
 */
final class KnxLandingTest extends TestCase
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

    public function test_super_admin_can_reach_the_knx_landing_page(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/bertels/knx')
            ->assertSuccessful()
            ->assertSee('KNX')
            ->assertSee(__('core::knx.heading'));
    }

    public function test_no_other_role_can_reach_the_knx_landing_page(): void
    {
        foreach (array_diff(self::ALL_ROLES, ['super_admin']) as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/bertels/knx')
                ->assertForbidden();
        }

        $this->actingAs($this->userWithRole(null))
            ->get('/bertels/knx')
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/bertels/knx')->assertRedirect('/login');
    }

    public function test_the_bertels_navigation_includes_a_knx_item_that_opens_in_a_new_tab(): void
    {
        $this->withoutVite();

        $response = $this->actingAs($this->userWithRole('super_admin'))->get('/bertels');

        $response->assertSuccessful();
        $response->assertSee(route('bertels.knx'), false);
        $response->assertSee('target="_blank"', false);
    }

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
            'has_email_authentication' => true,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->refresh();
    }
}
