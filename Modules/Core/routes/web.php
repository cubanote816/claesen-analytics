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

// Session-cookie login for browser-first SPAs (Safety PWA, Sport, etc.) — needs the 'web'
// middleware group (session, CSRF) that api.php's stateless 'api' group does not provide.
Route::post('api/v1/auth/login/spa', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'loginSpa'])
    ->name('auth.login.spa');

// CLA-344: dedicated login for the Client Portal — same session-cookie contract as
// login/spa, but only users with the 'client' role may establish a session here.
Route::post('api/v1/auth/login/client-portal', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'loginClientPortal'])
    ->name('auth.login.client-portal');

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
