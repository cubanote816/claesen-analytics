<?php

use Illuminate\Support\Facades\Route;
use Modules\Safety\Http\Controllers\SafetyController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('safeties', SafetyController::class)->names('safety');
});
