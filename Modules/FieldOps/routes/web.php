<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldOps\Http\Controllers\LuminaireController;
use Modules\FieldOps\Http\Controllers\LuminaireFrameTypeImageController;

Route::middleware(['auth'])
    ->prefix('fieldops/catalogs/luminaire-frame-types')
    ->name('fieldops.catalogs.luminaire-frame-types.')
    ->group(function () {
        Route::post('/image', [LuminaireFrameTypeImageController::class, 'store'])
            ->name('image-store');
    });

Route::middleware(['auth', \Modules\Core\Http\Middleware\BrowserLocaleMiddleware::class])
    ->prefix('fieldops/luminaire-frame-editor/luminaires')
    ->name('fieldops.luminaire-frame-editor.luminaires.')
    ->group(function () {
        Route::post('/', [LuminaireController::class, 'storeFromBackoffice'])
            ->name('store');
        Route::get('/{luminaire}', [LuminaireController::class, 'showFromBackoffice'])
            ->name('show');
        Route::patch('/{luminaire}', [LuminaireController::class, 'updateFromBackoffice'])
            ->name('update');
        Route::post('/{luminaire}/replacement', [LuminaireController::class, 'replaceFromBackoffice'])
            ->name('replace');
    });
