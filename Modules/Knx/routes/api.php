<?php

use Illuminate\Support\Facades\Route;
use Modules\Knx\Http\Controllers\Auth\AuthController;
use Modules\Knx\Http\Controllers\Auth\SessionController;
use Modules\Knx\Http\Controllers\ClientController;
use Modules\Knx\Http\Controllers\ConflictController;
use Modules\Knx\Http\Controllers\FieldNotificationController;
use Modules\Knx\Http\Controllers\PlanningController;
use Modules\Knx\Http\Controllers\ProjectController;

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

        // K2 — clients and projects.
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        // {code} is a string ("C1618"), not a numeric id: constrain it so it can
        // never swallow the nested routes below (stats, devices, activity...).
        Route::get('projects/{code}', [ProjectController::class, 'show'])
            ->where('code', '[A-Za-z0-9._-]+')
            ->name('projects.show');
        Route::get('projects/{code}/stats', [ProjectController::class, 'stats'])
            ->where('code', '[A-Za-z0-9._-]+')
            ->name('projects.stats');

        // K3 — the dossier of a project.
        Route::get('projects/{code}/devices', [ProjectController::class, 'devices'])
            ->where('code', '[A-Za-z0-9._-]+')
            ->name('projects.devices');
        Route::get('projects/{code}/activity', [ProjectController::class, 'activity'])
            ->where('code', '[A-Za-z0-9._-]+')
            ->name('projects.activity');
        // K4 — the field inbox. ack-all is declared first: it would otherwise be
        // a candidate match for {id} in a stricter route model binding setup.
        Route::post('notifications/ack-all', [FieldNotificationController::class, 'ackAll'])->name('notifications.ack-all');
        Route::get('notifications', [FieldNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/ack', [FieldNotificationController::class, 'ack'])->name('notifications.ack');
        // K5 — technicians and planning.
        Route::get('technicians', [PlanningController::class, 'technicians'])->name('technicians.index');

        Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::put('planning', [PlanningController::class, 'store'])->name('planning.store');
        Route::delete('planning', [PlanningController::class, 'destroy'])->name('planning.destroy');
        // K6 — the Conflictencentrum.
        Route::get('conflicts', [ConflictController::class, 'index'])->name('conflicts.index');
        Route::get('conflicts/{id}', [ConflictController::class, 'show'])->name('conflicts.show');
        Route::patch('conflicts/{id}', [ConflictController::class, 'update'])->name('conflicts.update');
        // K7: zones
        // K8: documents, reports
        // K9: functions, tests
    });
});
