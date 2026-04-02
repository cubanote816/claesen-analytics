<?php

use Illuminate\Support\Facades\Route;
use Modules\Cafca\Http\Controllers\CafcaController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('cafcas', CafcaController::class)->names('cafca');
});
