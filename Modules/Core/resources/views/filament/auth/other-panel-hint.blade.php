{{--
    CLA-611 (seguimiento): el login deja de mentir cuando alguien entra por el panel equivocado.

    Por qué existe: la denegación por panel vive en `attemptWhen()` de Modules\Core\Filament\Pages\Auth
    \Login (a propósito: un 403 a una sesión ya autenticada la dejaría sin poder cerrar sesión), y
    cuando se dispara el login muestra el mensaje genérico de credenciales. Un usuario de otra
    empresa que escribe bien su contraseña y su código de MFA ve «vuelve a /login» sin ninguna
    explicación, y lo razonable es pensar que el código estaba mal. Reproducido y verificado.

    Por qué es un texto ESTÁTICO y con enlace, en vez de un mensaje específico del tipo «tus
    credenciales son correctas pero no tienes acceso a este panel»: eso último revela que la
    contraseña era válida (enumeración de cuentas). Esto arregla la trampa sin filtrar nada.

    Clases de login-theme.blade.php (CSS plano), nunca utilidades de Tailwind: el panel bertels no
    tiene ->viteTheme(), así que las utilidades que sólo usan las vistas del login no llegan a
    compilarse ahí (fue exactamente el defecto del icono de Microsoft de CLA-603).
--}}
@php
    use Filament\Facades\Filament;

    $panelId = Filament::getCurrentOrDefaultPanel()?->getId();

    // El otro panel es el que no es éste; en la práctica sólo hay dos con login propio.
    $otherPanelId = $panelId === 'bertels' ? 'admin' : 'bertels';
    $otherSiteKey = config("organizations.panel_sites.{$otherPanelId}");
    $otherBrand = $otherSiteKey === null ? null : config("organizations.login_brand.{$otherSiteKey}");

    // Si el otro panel no tiene marca configurada, no se inventa un nombre: no se muestra nada.
    $otherPanelExists = Filament::getPanel($otherPanelId) !== null;
@endphp

@if ($otherBrand !== null && $otherPanelExists && $panelId !== $otherPanelId)
    <p class="cafca-login-other-panel">
        {{ __('core::auth.other_panel.hint', ['company' => $otherBrand['logo_alt']]) }}
        <a href="{{ route("filament.{$otherPanelId}.auth.login") }}">{{ __('core::auth.other_panel.link') }}</a>
    </p>
@endif
