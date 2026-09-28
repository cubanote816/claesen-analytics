<?php

use App\Http\Middleware\SetPanelLocale;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\ResolveRequestSite;
use Modules\Core\Http\Middleware\SetPublicApiCacheHeaders;
use Modules\Website\Http\Controllers\ConsultationController;
use Modules\Website\Http\Controllers\ContactController;
use Modules\Website\Http\Controllers\LegalDocumentController;
use Modules\Website\Http\Controllers\MediaSlotController;
use Modules\Website\Http\Controllers\PagePublicationController;
use Modules\Website\Http\Controllers\ProjectController;
use Modules\Website\Http\Controllers\SiteContentController;

Route::prefix('v1/website')->middleware([
    ResolveRequestSite::class,
    SetPanelLocale::class,
    SetPublicApiCacheHeaders::class,
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
    Route::post('/contact-email', [ContactController::class, 'store'])->middleware($intakeThrottle);

    Route::get('/settings', [SiteContentController::class, 'settings']);
    Route::get('/announcements', [SiteContentController::class, 'announcements']);

    // CLA-611: what the static site's build may index, per page and locale. The
    // consumer is the build's sync, which writes the response verbatim to the
    // snapshot it reads (see the controller for why it is not enveloped).
    Route::get('/publication-manifest', [PagePublicationController::class, 'manifest']);

    // CLA-479 (dynamic part): per-site legal documents (privacy/cookies/terms).
    Route::get('/legal/{docId}', [LegalDocumentController::class, 'show']);

    // CLA-481: named media slots with dimensions, checksums and per-locale
    // alt/caption for the frontend build.
    Route::get('/media/slots', [MediaSlotController::class, 'index']);
});
