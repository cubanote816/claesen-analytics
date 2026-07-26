<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldOps\Http\Controllers\FieldOpsMediaController;
use Modules\FieldOps\Http\Controllers\LuminaireController;
use Modules\FieldOps\Http\Controllers\LuminaireFrameTypeImageController;

Route::middleware(['auth'])
    ->prefix('fieldops/catalogs/luminaire-frame-types')
    ->name('fieldops.catalogs.luminaire-frame-types.')
    ->group(function () {
        Route::post('/image', [LuminaireFrameTypeImageController::class, 'store'])
            ->name('image-store');
    });

// Serves photos/videos/documents (Complex/Terrain/Structure/ElectricalBoard/Luminaire)
// for the Filament backoffice, which authenticates via the web session guard, not
// Sanctum — the API route in Routes/api.php (auth:sanctum) is for the field-app/PWA
// clients only. Reuses FieldOpsMediaController::show() as-is; it already scopes to
// FieldOps-owned media via assertFieldOpsMedia(). EnsurePanelAccess (not just `auth`)
// keeps this consistent with the panel's own trust boundary (CLAUDE.md rule 5):
// without it, a project_manager/client account — blocked from the panel UI itself —
// could still fetch any media by id through this route with nothing but a valid session.
Route::middleware(['auth', \Modules\Core\Http\Middleware\EnsurePanelAccess::class])
    ->prefix('fieldops/media')
    ->name('fieldops.admin.media.')
    ->group(function () {
        Route::get('/{media}', [FieldOpsMediaController::class, 'show'])->name('show');
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
