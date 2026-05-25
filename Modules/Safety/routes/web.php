<?php

use Illuminate\Support\Facades\Route;
use Modules\Safety\Http\Controllers\SafetyController;
use Modules\Safety\Http\Controllers\SafetyFileController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('safeties', SafetyController::class)->names('safety');
});

Route::middleware('auth')->prefix('safety/files')->name('safety.admin.')->group(function () {
    Route::get('inspections/{inspection}/pdf', [SafetyFileController::class, 'pdf'])->name('pdf');
    Route::get('answers/{answer}/photo',       [SafetyFileController::class, 'photo'])->name('photo');
});
