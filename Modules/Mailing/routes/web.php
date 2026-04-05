<?php

use Illuminate\Support\Facades\Route;
use Modules\Mailing\Http\Controllers\MailingController;
use Modules\Mailing\Http\Controllers\UnsubscribeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('mailings', MailingController::class)->names('mailing');
});

// Public Unsubscribe Routes
Route::get('prospects/unsubscribe/{prospect}/{token}', [UnsubscribeController::class, 'show'])->name('prospects.unsubscribe');
Route::post('prospects/unsubscribe/{prospect}/{token}', [UnsubscribeController::class, 'store'])->name('prospects.unsubscribe.confirm');
