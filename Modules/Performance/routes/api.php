<?php

use Illuminate\Support\Facades\Route;
use Modules\Performance\Http\Controllers\PerformanceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('performance')->group(function () {
        Route::get('stats/{id}', [PerformanceController::class, 'stats']);
        Route::get('efficiency-ranking', [PerformanceController::class, 'efficiencyRanking']);
    });
    
    Route::apiResource('performances', PerformanceController::class)->names('performance');
});
