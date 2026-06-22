<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldOps\Http\Controllers\ComplexController;
use Modules\FieldOps\Http\Controllers\LuminaireController;
use Modules\FieldOps\Http\Controllers\LuminaireFrameController;
use Modules\FieldOps\Http\Controllers\StructureController;
use Modules\FieldOps\Http\Controllers\TerrainController;

Route::middleware(['auth:sanctum'])->prefix('v1/fieldops')->group(function () {
    // Complexes
    Route::get('/complexes', [ComplexController::class, 'index']);
    Route::get('/complexes/{complex}', [ComplexController::class, 'show']);

    // Terrain catalogs
    Route::get('/terrain-types', [TerrainController::class, 'types']);

    // Structures
    Route::get('/structures', [StructureController::class, 'index']);
    Route::get('/structures/{structure}', [StructureController::class, 'show']);

    // LuminaireFrames + Luminaires
    Route::get('/luminaire-frames/{frame}/luminaires', [LuminaireFrameController::class, 'luminaires']);
    Route::get('/luminaires/{luminaire}', [LuminaireController::class, 'show']);
});
