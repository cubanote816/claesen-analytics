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
 * The first enforcement layer the ADR names: "paneles y logins".
 *
 * **CLA-611 (2026-09-29): la frontera del panel admin ya no está detrás del flag.**
 * Mientras `organizations.enforce` estaba apagado (su valor por defecto), este
 * archivo fijaba por escrito que un usuario de otra organización *podía* entrar al
 * panel admin. Eso era el agujero que el ADR D10 señalaba: la única barrera era que
 * todavía no existía ningún usuario de Bertels. Al abrir el panel `bertels` a la
 * organización dueña, esa barrera se cierra en el código y no en una configuración
 * apagable, así que los dos tests que documentaban el hueco ahora documentan la
 * frontera. El flag sigue gobernando las reglas de negocio por organización (D4).
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

    public function test_a_user_outside_claesens_organization_is_rejected_from_the_admin_panel_regardless_of_the_flag(): void
    {
        config(['organizations.enforce' => false]);

        $user = $this->userInFixtureOrganization();

        // CLA-611: la admisión al panel es una frontera de acceso, no una regla de
        // negocio: no puede depender de un flag que en producción está apagado.
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
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

    public function test_a_manipulated_url_to_the_admin_panel_is_forbidden_for_another_organization_regardless_of_the_flag(): void
    {
        config(['organizations.enforce' => false]);

        $user = $this->userInFixtureOrganization();

        $this->actingAs($user)->get('/')->assertForbidden();
    }

    private function userInFixtureOrganization(): User
    {
        $fixtureOrg = Organization::factory()->create(['slug' => 'fixture-non-claesen-org']);

        $user = User::factory()->create([
            'organization_id' => $fixtureOrg->id,
            'is_active' => true,
            'password_set_at' => now(),
            // CLA-464 (ADR D8): admin is now forced to set up MFA before
            // reaching the dashboard, a concern separate from what this test
            // isolates (organization enforcement).
            'has_email_authentication' => true,
        ]);
        $user->assignRole('admin');

        return $user->refresh();
    }
}
