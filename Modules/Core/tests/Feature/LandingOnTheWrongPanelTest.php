<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Auth\Login;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Aterrizar en el panel equivocado lleva al panel propio, no de vuelta al formulario.
 *
 * Caso reportado y reproducido: un usuario de Electro Bertels entra por el panel de Claesen,
 * escribe bien su contraseña y su código de MFA. El código **se valida y se consume** en el
 * desafío, que ocurre antes de decidir el panel; después `attemptWhen` lo niega, y el resultado
 * es volver al formulario sin mensaje y —si reintenta con el mismo código— «The code you entered
 * is invalid», porque ya se usó. Indistinguible de un código roto, y el usuario no había hecho
 * nada mal.
 *
 * Estos tests ejercen la decisión sin el desafío de MFA (usuarios con `has_email_authentication`
 * en false), que es donde vive el cambio: la negativa sólo se mantiene si no puede entrar a
 * **ningún** panel.
 */
final class LandingOnTheWrongPanelTest extends TestCase
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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function userWithoutMfa(string $email, ?Organization $organization, string $password = 'Secret123!'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'has_email_authentication' => false,
            'organization_id' => $organization?->id,
        ]);
    }

    public function test_a_bertels_user_entering_by_the_claesen_panel_lands_in_its_own(): void
    {
        $user = $this->userWithoutMfa('bertels@example.test', Organization::query()->where('slug', 'electro-bertels')->first());

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'Secret123!'])
            ->call('authenticate')
            ->assertRedirect(Filament::getPanel('bertels')->getUrl());

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_without_access_to_any_panel_is_still_refused(): void
    {
        // Ni Claesen ni Bertels: aquí la negativa es la correcta, y tiene que seguir siéndolo.
        $outsider = $this->userWithoutMfa('outsider@example.test', Organization::factory()->create(['slug' => 'ajena']));

        Livewire::test(Login::class)
            ->fillForm(['email' => $outsider->email, 'password' => 'Secret123!'])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_a_claesen_user_entering_by_its_own_panel_keeps_going_home_as_before(): void
    {
        $claesen = $this->userWithoutMfa('claesen@example.test', Organization::query()->find(Organization::claesenId()));

        Livewire::test(Login::class)
            ->fillForm(['email' => $claesen->email, 'password' => 'Secret123!'])
            ->call('authenticate')
            // Sin desvío: el destino es el panel donde ya estaba, como siempre.
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertAuthenticatedAs($claesen);
    }
}
