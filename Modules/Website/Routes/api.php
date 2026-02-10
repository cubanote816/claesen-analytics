<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\ProjectController;
use Modules\Website\Http\Controllers\ConsultationController;

Route::prefix('v1/website')->group(function () {
    Route::get('/', function () {
        return response()->json(['status' => 'Claesen Website API is running', 'version' => '1.0']);
    });

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/categories', [ProjectController::class, 'categories']);
    Route::get('/projects/years', [ProjectController::class, 'years']);
    Route::get('/projects/{slug}', [ProjectController::class, 'show']);

    Route::post('/consultations', [ConsultationController::class, 'store']);
});
