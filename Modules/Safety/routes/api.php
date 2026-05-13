<?php

use Illuminate\Support\Facades\Route;
use Modules\Safety\Http\Controllers\AuthController;
use Modules\Safety\Http\Controllers\InspectionController;
use Modules\Safety\Http\Controllers\NotificationController;
use Modules\Safety\Http\Middleware\EnsureSafetyAccess;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
 *--------------------------------------------------------------------------
*/

Route::post('v1/login', [AuthController::class, 'login'])->name('safety.api.login');
// Auth & Profile
Route::middleware('auth:sanctum')->group(function () {
    Route::get('v1/me', [AuthController::class, 'me'])->name('safety.api.me');
});

// Notifications
Route::middleware('auth:sanctum')->prefix('v1/safety/notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('safety.api.notifications.index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('safety.api.notifications.unread');
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('safety.api.notifications.mark-read');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('safety.api.notifications.mark-all-read');
});

Route::middleware(['auth:sanctum', EnsureSafetyAccess::class])
    ->prefix('v1/safety')
    ->group(function () {
        Route::get('checklists/active', [\Modules\Safety\Http\Controllers\ChecklistController::class, 'active'])->name('safety.api.checklists.active');
        Route::get('projects', [\Modules\Safety\Http\Controllers\ProjectController::class, 'index'])->name('safety.api.projects.index');

        Route::prefix('inspections')->name('safety.api.inspections.')->group(function () {
            Route::get('/', [InspectionController::class, 'index'])->name('index');
            Route::get('stats', [InspectionController::class, 'stats'])->name('stats');
            Route::post('/', [InspectionController::class, 'store'])->name('store');
        });

        Route::prefix('notifications')->name('safety.api.notifications.')->group(function () {
            Route::get('/', [\Modules\Safety\Http\Controllers\NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [\Modules\Safety\Http\Controllers\NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('{id}/read', [\Modules\Safety\Http\Controllers\NotificationController::class, 'markAsRead'])->name('mark-as-read');
            Route::post('read-all', [\Modules\Safety\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        });
    });
