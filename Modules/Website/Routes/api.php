<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\ProjectController;
use Modules\Website\Http\Controllers\ConsultationController;
use Modules\Website\Http\Controllers\SiteContentController;

Route::prefix('v1/website')->middleware([
    \Modules\Core\Http\Middleware\ResolveRequestSite::class,
    \App\Http\Middleware\SetPanelLocale::class,
    \Modules\Core\Http\Middleware\SetPublicApiCacheHeaders::class,
])->group(function () {
    Route::get('/', function () {
        return response()->json(['status' => 'Claesen Website API is running', 'version' => '1.0']);
    });

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/categories', [ProjectController::class, 'categories']);
    Route::get('/projects/years', [ProjectController::class, 'years']);
    Route::get('/projects/{slug}', [ProjectController::class, 'show']);

    Route::post('/consultations', [ConsultationController::class, 'store']);
    Route::post('/contact-email', [\Modules\Website\Http\Controllers\ContactController::class, 'store']);

    Route::get('/settings', [SiteContentController::class, 'settings']);
    Route::get('/announcements', [SiteContentController::class, 'announcements']);
});
