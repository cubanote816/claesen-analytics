<?php

use Illuminate\Support\Facades\Route;
use Modules\Safety\Http\Controllers\AuthController;
use Modules\Safety\Http\Controllers\InspectionController;
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

Route::post('/login', [AuthController::class, 'login'])->name('safety.api.login');

Route::middleware(['auth:sanctum', EnsureSafetyAccess::class])
    ->prefix('safety')
    ->group(function () {
        Route::get('checklists/active', [\Modules\Safety\Http\Controllers\ChecklistController::class, 'active'])->name('safety.api.checklists.active');
        Route::get('projects', [\Modules\Safety\Http\Controllers\ProjectController::class, 'index'])->name('safety.api.projects.index');

        Route::prefix('inspections')->name('safety.api.inspections.')->group(function () {
            Route::post('/', [InspectionController::class, 'store'])->name('store');
        });

        Route::prefix('notifications')->name('safety.api.notifications.')->group(function () {
            Route::get('/', [\Modules\Safety\Http\Controllers\NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [\Modules\Safety\Http\Controllers\NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('{id}/read', [\Modules\Safety\Http\Controllers\NotificationController::class, 'markAsRead'])->name('mark-as-read');
            Route::post('read-all', [\Modules\Safety\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        });
    });
