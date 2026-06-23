<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;

Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'login']);
});

// Public — exchange one-time activation code for a limited setup:password token.
// Rate-limited to prevent brute-force on the code space.
Route::middleware(['throttle:5,1'])
    ->post('v1/auth/activate',
        [\Modules\Core\Http\Controllers\Auth\ExchangeActivationCodeController::class, 'exchange'])
    ->name('auth.activate');

// Requires a setup:password Sanctum token (issued by /auth/activate).
// Intentionally excluded from EnsurePasswordIsSet — it is the cure.
Route::middleware(['auth:sanctum', 'abilities:setup:password'])
    ->prefix('v1/auth')
    ->group(function () {
        Route::post('/setup-password',
            [\Modules\Core\Http\Controllers\Auth\SetupPasswordController::class, 'setupViaToken'])
            ->name('auth.setup-password.api');
    });

// Logout is intentionally outside EnsurePasswordIsSet so pending-setup accounts can log out.
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('/auth/logout', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'logout']);
});

// Protected Core routes — require both valid token AND completed password setup.
Route::middleware(['auth:sanctum', \Modules\Core\Http\Middleware\EnsurePasswordIsSet::class])
    ->prefix('v1')
    ->group(function () {
        Route::get('/auth/introspect', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'introspect']);
        Route::get('/me', [\Modules\Core\Http\Controllers\Auth\ProfileController::class, 'me']);
        Route::apiResource('cores', CoreController::class)->names('core');
    });
