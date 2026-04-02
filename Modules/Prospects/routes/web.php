<?php

use Illuminate\Support\Facades\Route;
use Modules\Prospects\Http\Controllers\ProspectsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('prospects', ProspectsController::class)->names('prospects');
});
