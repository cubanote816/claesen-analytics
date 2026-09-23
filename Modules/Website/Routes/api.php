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

    // F4/CLA-475: "rate limit por IP" — Laravel's default ThrottleRequests
    // keys an unauthenticated request by IP. Computed once at boot from
    // config('website.intake_hardening.rate_limit'), same pattern as every
    // other `throttle:` route elsewhere in the repo, just config-driven
    // instead of a hardcoded pair so tests can override it.
    $intakeThrottle = sprintf(
        'throttle:%d,%d',
        config('website.intake_hardening.rate_limit.max_attempts', 10),
        config('website.intake_hardening.rate_limit.decay_minutes', 1)
    );

    Route::post('/consultations', [ConsultationController::class, 'store'])->middleware($intakeThrottle);
    Route::post('/contact-email', [\Modules\Website\Http\Controllers\ContactController::class, 'store'])->middleware($intakeThrottle);

    Route::get('/settings', [SiteContentController::class, 'settings']);
    Route::get('/announcements', [SiteContentController::class, 'announcements']);
});
