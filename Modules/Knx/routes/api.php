<?php

use Illuminate\Support\Facades\Route;
use Modules\Knx\Http\Controllers\Auth\AuthController;
use Modules\Knx\Http\Controllers\Auth\SessionController;

/*
 * KNX installation API — the contract consumed by Kantoor (office) and Veld
 * (field). See docs/BACKEND-API.md and docs/BACKEND-API-ZONES.md in the
 * electro-bertels-kantoor repo.
 *
 * Everything lives under /api/v1/knx (this provider's `api` prefix plus the
 * v1/knx below) and, except the session endpoints, behind `auth:sanctum` and
 * `organization:electro-bertels` so no Claesen token can reach it.
 */

Route::prefix('v1/knx')->name('knx.')->group(function (): void {
    // K1 — session. Login/refresh are unauthenticated by definition and throttled
    // like the rest of the app's logins; logout needs a token to revoke.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1')
        ->name('auth.refresh');

    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    Route::middleware(['auth:sanctum', 'organization:electro-bertels'])->group(function (): void {
        Route::get('me/session', [SessionController::class, 'show'])->name('me.session');

        // K2: clients, projects (+stats)
        // K3: projects/{code}/devices|activity
        // K4: notifications (+ack)
        // K5: technicians, planning
        // K6: conflicts
        // K7: zones
        // K8: documents, reports
        // K9: functions, tests
    });
});
