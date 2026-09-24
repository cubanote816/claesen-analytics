<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('cores', CoreController::class)->names('core');
    Route::get('/heartbeat', \Modules\Core\Http\Controllers\HeartbeatController::class)->name('core.heartbeat');
});

// Microsoft Azure Auth Routes
Route::prefix('auth/microsoft')->group(function () {
    Route::get('/redirect', [\Modules\Core\Http\Controllers\Auth\MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('/callback', [\Modules\Core\Http\Controllers\Auth\MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
});

// Alias for Frontend/PWA
Route::get('api/v1/auth/microsoft/redirect', [\Modules\Core\Http\Controllers\Auth\MicrosoftAuthController::class, 'redirect']);
Route::get('api/v1/auth/microsoft/callback', [\Modules\Core\Http\Controllers\Auth\MicrosoftAuthController::class, 'callback']);

// CLA-344: dedicated login for the Client Portal — same session-cookie contract as
// the removed login/spa, but only users with the 'client' role may establish a
// session here.
Route::post('api/v1/auth/login/client-portal', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'loginClientPortal'])
    ->name('auth.login.client-portal');

// CLA-363: dedicated login for Sport, same pattern as login/client-portal. Safety
// already has its own gated login — see Modules/Safety/Http/Controllers/AuthController.
Route::post('api/v1/auth/login/sport', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'loginSport'])
    ->name('auth.login.sport');

// Password setup — for users provisioned by an admin (Azure-first flow, Filament/web).
// Protected by web session only (Auth::login() called in callback before redirect here).
// Intentionally outside the Filament panel so EnsurePasswordIsSet does not block it.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/auth/setup-password',
        [\Modules\Core\Http\Controllers\Auth\SetupPasswordController::class, 'show'])
        ->name('auth.setup-password');
    Route::post('/auth/setup-password',
        [\Modules\Core\Http\Controllers\Auth\SetupPasswordController::class, 'store'])
        ->name('auth.setup-password.store');
});

// Welcome page for authenticated users without panel access (e.g. project_manager).
// Intentionally outside the Filament panel so EnsurePanelAccess does not block it.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/auth/no-access',
        [\Modules\Core\Http\Controllers\Auth\NoPanelAccessController::class, 'show'])
        ->name('auth.no-access');
});

// F1/P4+P6 (CLA-460 cont.): audited super_admin panel switch. Intentionally
// outside any Filament panel's route group — see SwitchPanelController's
// docblock. Authorization (super_admin only) is enforced inside the
// controller, not via route middleware, matching this file's existing
// convention for non-panel routes.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/switch-panel/{panel}', \Modules\Core\Http\Controllers\SwitchPanelController::class)
        ->name('core.switch-panel');
});

// CLA-602 (Fase 1 de la app KNX de Electro Bertels): punto de entrada desde
// el menú del panel Bertels, deliberadamente fuera del shell de Filament —
// ver KnxLandingController. Mismo patrón que las rutas anteriores: fuera de
// cualquier grupo de panel, con la autorización dentro del controller.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/bertels/knx', \Modules\Core\Http\Controllers\KnxLandingController::class)
        ->name('bertels.knx');
});
