<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldOps\Http\Controllers\LuminaireFrameTypeImageController;

Route::middleware(['auth'])
    ->prefix('fieldops/catalogs/luminaire-frame-types')
    ->name('fieldops.catalogs.luminaire-frame-types.')
    ->group(function () {
        Route::post('/image', [LuminaireFrameTypeImageController::class, 'store'])
            ->name('image-store');
    });
