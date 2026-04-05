<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CoreController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('cores', CoreController::class)->names('core');
});

// Microsoft Azure Auth Routes
Route::prefix('auth/microsoft')->group(function () {
    Route::get('/redirect', [\Modules\Core\Http\Controllers\Auth\MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('/callback', [\Modules\Core\Http\Controllers\Auth\MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
});
