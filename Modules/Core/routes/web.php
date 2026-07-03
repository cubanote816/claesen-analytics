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
