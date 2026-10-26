<?php

use Illuminate\Support\Facades\Route;
use Modules\Prospects\Http\Controllers\ProspectsController;

Route::middleware(['auth:sanctum', 'organization:claesen'])->prefix('v1')->group(function () {
    Route::apiResource('prospects', ProspectsController::class)->names('prospects');
});
