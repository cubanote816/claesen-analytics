<?php

use Illuminate\Support\Facades\Route;
use Modules\Mailing\Http\Controllers\MailingController;
use Modules\Mailing\Http\Controllers\UnsubscribeController;

// Removed legacy web route to avoid conflict with Filament root path


// Public Unsubscribe Routes
Route::get('prospects/unsubscribe/{prospect}/{token}', [UnsubscribeController::class, 'show'])->name('prospects.unsubscribe');
Route::post('prospects/unsubscribe/{prospect}/{token}', [UnsubscribeController::class, 'store'])->name('prospects.unsubscribe.confirm');
