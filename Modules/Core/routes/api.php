<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;

Route::prefix('v1/auth')->group(function () {
    // Bearer-token login — non-browser clients and legacy integrations.
    Route::post('/login', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'login']);
    // loginSpa() lives in routes/web.php — it needs session/CSRF (the 'web' middleware
    // group), which this api.php file's 'api' middleware group does not provide.
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
        Route::post('/auth/change-password', \Modules\Core\Http\Controllers\Auth\ChangePasswordController::class)
            ->name('auth.change-password');
        Route::get('/me', [\Modules\Core\Http\Controllers\Auth\ProfileController::class, 'me']);
        Route::apiResource('cores', CoreController::class)->names('core');

        Route::prefix('user/preferences')->group(function () {
            Route::get('/', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'show']);
            Route::put('/', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'update']);
            Route::put('/advanced', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'updateAdvanced']);
            Route::get('/defaults', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'defaults']);
            Route::post('/reset', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'resetToDefaults']);
            Route::get('/history', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'history']);
            Route::post('/validate', [\Modules\Core\Http\Controllers\UserPreferencesController::class, 'validateStructure']);
        });
    });
