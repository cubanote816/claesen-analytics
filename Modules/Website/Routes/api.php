<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\PageController;
use Modules\Website\Http\Controllers\ProjectController;
use Modules\Website\Http\Controllers\ConsultationController;

Route::prefix('v1/website')->group(function () {
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/categories', [ProjectController::class, 'categories']);
    Route::get('/projects/years', [ProjectController::class, 'years']);
    Route::get('/projects/{slug}', [ProjectController::class, 'show']);

    Route::post('/consultations', [ConsultationController::class, 'store']);
});
