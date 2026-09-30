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
 * El punto de entrada a la app KNX de oficina (`bertels.knx`).
 *
 * CLA-602 lo dejó dentro del panel Bertels y reutilizando a propósito
 * `User::canAccessPanel('bertels')`, «para que ambos sigan la misma fuente de verdad si
 * cambia». La fuente cambió (CLA-611): el panel ya no es solo `super_admin`, ahora también
 * admite a los usuarios de la organización dueña del sitio, porque el cliente tiene que
 * poder aprobar sus páginas.
 *
 * Pero KNX es una app **de personal**, no del cliente. «Puede entrar al panel» dejó de
 * implicar «es de la casa», así que el enlace y la ruta no pueden seguir colgando de esa
 * comprobación a secas: si lo hicieran, el usuario del cliente vería la entrada al KNX.
 *
 * Esta clase fija la frontera. El caso del cliente es el que importa.
 */
final class KnxOfficeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        Site::factory()->create([
            'organization_id' => $organization->id,
            'key' => 'electro-bertels',
            'status' => Site::STATUS_ACTIVE,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('bertels'));
    }

    private function userIn(?Organization $organization, ?string $role = null): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization?->id,
            'is_active' => true,
        ]);

        if ($role !== null) {
            $user->assignRole(Role::findByName($role, 'web'));
        }

        return $user;
    }

    private function bertelsOrganization(): Organization
    {
        return Organization::query()->where('slug', 'electro-bertels')->firstOrFail();
    }

    public function test_a_super_admin_still_reaches_the_office_app(): void
    {
        $superAdmin = $this->userIn(Organization::query()->find(Organization::claesenId()), 'super_admin');

        $this->actingAs($superAdmin)->get('/bertels/knx')->assertOk();
    }

    public function test_a_client_user_of_the_bertels_organization_does_not(): void
    {
        // Entra al panel (aprueba páginas), pero no es personal de la casa: la app de
        // oficina no es suya.
        $client = $this->userIn($this->bertelsOrganization(), 'viewer');

        $this->assertTrue(
            $client->canAccessPanel(Filament::getPanel('bertels')),
            'el cliente sí debe entrar al panel: para eso se abrió',
        );

        $this->actingAs($client)->get('/bertels/knx')->assertForbidden();
    }

    public function test_a_claesen_user_does_not_reach_it_either(): void
    {
        $claesen = $this->userIn(Organization::query()->find(Organization::claesenId()), 'admin');

        $this->actingAs($claesen)->get('/bertels/knx')->assertForbidden();
    }
}
