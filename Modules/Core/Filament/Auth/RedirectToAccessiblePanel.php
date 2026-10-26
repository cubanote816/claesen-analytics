<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Aterriza a cada quien en el panel que le corresponde.
 *
 * Por qué existe, y es un caso reportado y reproducido: la denegación por panel vive dentro de
 * `attemptWhen()` en `Modules\Core\Filament\Pages\Auth\Login` — a propósito, para no 403ear una
 * sesión ya autenticada y dejarla sin poder cerrar sesión. Pero el desafío de MFA ocurre ANTES:
 * el código se valida y **se consume**, y sólo después se decide el panel. Así que un usuario de
 * Electro Bertels que entra por el panel de Claesen escribe bien su contraseña y su código, el
 * código se gasta, y vuelve al formulario sin ningún mensaje — y al reintentar con el mismo
 * código recibe «invalid», porque ya se usó. El síntoma es indistinguible de un código roto.
 *
 * Lo correcto no es explicar la negativa (eso hace el aviso del formulario) sino no llegar a
 * ella: si el código era válido y la persona pertenece a otro panel, se le autentica y se le
 * lleva al suyo. La frontera no se toca — sólo se le manda donde **sí** puede entrar; si no
 * puede entrar a ninguno, la denegación de siempre se mantiene.
 *
 * Se ata al **contrato** `LoginResponse`, que es el punto de extensión que Filament ofrece para
 * esto: el login devuelve `app(LoginResponse::class)`, y sin esta clase el destino sería
 * `redirect()->intended(Filament::getUrl())` — el panel equivocado, otra vez.
 */
class RedirectToAccessiblePanel implements LoginResponse
{
    /** Clave de sesión con el panel al que hay que llevar a esta persona. */
    public const SESSION_KEY = 'filament.login.redirect_to_panel';

    public function toResponse($request): RedirectResponse | Redirector
    {
        $panelId = session()->pull(self::SESSION_KEY);
        $panel = is_string($panelId) ? Filament::getPanel($panelId) : null;

        if ($panel !== null) {
            return redirect()->to($panel->getUrl());
        }

        // El caso normal, igual que el default de Filament.
        return redirect()->intended(Filament::getUrl());
    }
}
