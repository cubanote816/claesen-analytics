<?php

use Illuminate\Support\Facades\Route;
use Modules\Mailing\Http\Controllers\MailingController;
use Modules\Mailing\Http\Controllers\UnsubscribeController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('mailings', MailingController::class)->names('mailing');
});

// Public API routes
Route::prefix('v1')->group(function () {
    Route::post('unsubscribe-direct', [UnsubscribeController::class, 'apiUnsubscribe'])->name('api.mailing.unsubscribe');
});
