<?php

use Illuminate\Support\Facades\Route;
use Modules\Mailing\Http\Controllers\MailingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('mailings', MailingController::class)->names('mailing');
});
