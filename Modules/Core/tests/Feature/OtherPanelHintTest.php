<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El aviso de «este no es tu panel» en las pantallas de login.
 *
 * Por qué existe: negarse a dejar entrar a un usuario de la otra empresa ocurre dentro de
 * `attemptWhen()` (a propósito, para no 403ear una sesión ya autenticada y dejarla sin poder
 * cerrar sesión), y en ese caso Filament muestra el mensaje genérico de credenciales. Un
 * usuario de Bertels que escribe bien su contraseña **y su código de MFA** en el panel de
 * Claesen vuelve al formulario sin ninguna explicación — reproducido antes de escribir esto —
 * y lo razonable es concluir que el código estaba mal.
 *
 * El aviso es texto estático con enlace: no dice «tus credenciales son correctas», porque eso
 * revelaría que la contraseña era válida. Sólo dice que aquí no, y dónde sí.
 *
 * No se prueba el caso del usuario denegado de punta a punta aquí: el síntoma era la falta de
 * explicación, y eso es lo que se fija. Que la denegación siga ocurriendo lo cubren
 * CanAccessPanelMatrixTest y PanelOrganizationEnforcementTest.
 */
final class OtherPanelHintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_claesen_login_points_at_the_bertels_panel(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSeeText(__('core::auth.other_panel.hint', ['company' => 'Electro Bertels']));
        $response->assertSee(route('filament.bertels.auth.login'), false);
    }

    public function test_the_bertels_login_points_at_the_claesen_panel(): void
    {
        $response = $this->get('/bertels/login');

        $response->assertOk();
        $response->assertSeeText(__('core::auth.other_panel.hint', ['company' => 'Claesen']));
        $response->assertSee(route('filament.admin.auth.login'), false);
    }

    public function test_the_hint_stays_off_the_other_auth_screens(): void
    {
        // Sólo en el login: en el reseteo de contraseña o en el desafío de MFA sería ruido, y
        // en el de MFA además confundiría justo cuando la persona está copiando un código.
        $response = $this->get('/bertels/password-reset/request');

        $response->assertOk();
        $response->assertDontSeeText(__('core::auth.other_panel.link'));
    }
}
