<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\EventController;

// Intentionally no auth:sanctum middleware — anonymous/pre-session events
// are a supported case. See EventController::store(). Throttled because the
// route is otherwise unauthenticated: no CORS/Sanctum barrier stops a direct
// (non-browser) POST from outside the internal apps.
Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
});
