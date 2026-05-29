<?php

use Illuminate\Support\Facades\Route;
use Modules\Mailing\Http\Controllers\MailingController;
use Modules\Mailing\Http\Controllers\TrackingController;
use Modules\Mailing\Http\Controllers\UnsubscribeController;

// Public Unsubscribe Routes
Route::get('prospects/unsubscribe/{prospect}/{token}', [UnsubscribeController::class, 'show'])->name('prospects.unsubscribe');
Route::post('prospects/unsubscribe/{prospect}/{token}', [UnsubscribeController::class, 'store'])->name('prospects.unsubscribe.confirm');

// MAI-013 — Open pixel (token may arrive with .gif suffix)
Route::get('mailing/track/open/{token}', [TrackingController::class, 'openPixel'])
    ->where('token', '[A-Za-z0-9]{64}(?:\.gif)?')
    ->name('mailing.track.open');

// MAI-014 — Click redirect
Route::get('mailing/track/click/{token}/{hash}', [TrackingController::class, 'clickRedirect'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->where('hash', '[a-f0-9]{12}')
    ->name('mailing.track.click');
