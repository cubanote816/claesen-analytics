<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('/me', [\Modules\Core\Http\Controllers\Auth\ProfileController::class, 'me']);
    Route::apiResource('cores', CoreController::class)->names('core');
});
