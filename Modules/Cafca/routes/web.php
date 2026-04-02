<?php

use Illuminate\Support\Facades\Route;
use Modules\Cafca\Http\Controllers\CafcaController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('cafcas', CafcaController::class)->names('cafca');
});
