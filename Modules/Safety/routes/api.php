<?php

use Illuminate\Support\Facades\Route;
use Modules\Safety\Http\Controllers\AuthController;
use Modules\Safety\Http\Controllers\InspectionController;
use Modules\Safety\Http\Middleware\EnsureProjectLeader;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::post('/login', [AuthController::class, 'login'])->name('safety.api.login');

Route::middleware(['auth:sanctum', EnsureProjectLeader::class])->group(function () {
    Route::prefix('inspections')->name('safety.api.inspections.')->group(function () {
        Route::post('/', [InspectionController::class, 'store'])->name('store');
    });
});
