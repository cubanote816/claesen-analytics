<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;

Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('/auth/logout', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'logout']);
    Route::get('/auth/introspect', [\Modules\Core\Http\Controllers\Auth\AuthController::class, 'introspect']);
    Route::get('/me', [\Modules\Core\Http\Controllers\Auth\ProfileController::class, 'me']);
    Route::apiResource('cores', CoreController::class)->names('core');
});
