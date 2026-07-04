<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldOps\Http\Controllers\ComplexController;
use Modules\FieldOps\Http\Controllers\ElectricalBoardController;
use Modules\FieldOps\Http\Controllers\FieldOpsMediaController;
use Modules\FieldOps\Http\Controllers\LuminaireController;
use Modules\FieldOps\Http\Controllers\LuminaireFrameController;
use Modules\FieldOps\Http\Controllers\StructureController;
use Modules\FieldOps\Http\Controllers\TerrainController;

Route::middleware(['auth:sanctum', \Modules\Core\Http\Middleware\SetLocaleFromHeader::class])
    ->prefix('v1/fieldops')->group(function () {
    // Complexes
    Route::get('/complexes', [ComplexController::class, 'index']);
    Route::post('/complexes', [ComplexController::class, 'store']);
    Route::get('/complexes/{complex}', [ComplexController::class, 'show']);
    Route::put('/complexes/{complex}', [ComplexController::class, 'update']);
    Route::patch('/complexes/{complex}', [ComplexController::class, 'update']);
    Route::delete('/complexes/{complex}', [ComplexController::class, 'destroy']);

    // Terrain catalogs
    Route::get('/terrain-types', [TerrainController::class, 'types']);

    // Terrains
    Route::get('/terrains', [TerrainController::class, 'index']);
    Route::post('/terrains', [TerrainController::class, 'store']);
    Route::get('/terrains/{terrain}', [TerrainController::class, 'show']);
    Route::put('/terrains/{terrain}', [TerrainController::class, 'update']);
    Route::patch('/terrains/{terrain}', [TerrainController::class, 'update']);
    Route::delete('/terrains/{terrain}', [TerrainController::class, 'destroy']);

    // Structures
    Route::get('/structures', [StructureController::class, 'index']);
    Route::post('/structures', [StructureController::class, 'store']);
    Route::get('/structures/{structure}', [StructureController::class, 'show']);
    Route::put('/structures/{structure}', [StructureController::class, 'update']);
    Route::patch('/structures/{structure}', [StructureController::class, 'update']);
    Route::delete('/structures/{structure}', [StructureController::class, 'destroy']);

    // LuminaireFrames
    Route::get('/luminaire-frames', [LuminaireFrameController::class, 'index']);
    Route::post('/luminaire-frames', [LuminaireFrameController::class, 'store']);
    Route::get('/luminaire-frames/{frame}', [LuminaireFrameController::class, 'show']);
    Route::put('/luminaire-frames/{frame}', [LuminaireFrameController::class, 'update']);
    Route::patch('/luminaire-frames/{frame}', [LuminaireFrameController::class, 'update']);
    Route::delete('/luminaire-frames/{frame}', [LuminaireFrameController::class, 'destroy']);
    Route::get('/luminaire-frames/{frame}/luminaires', [LuminaireFrameController::class, 'luminaires']);

    // Luminaires
    Route::post('/luminaires', [LuminaireController::class, 'store']);
    Route::get('/luminaires/{luminaire}', [LuminaireController::class, 'show']);
    Route::put('/luminaires/{luminaire}', [LuminaireController::class, 'update']);
    Route::patch('/luminaires/{luminaire}', [LuminaireController::class, 'update']);
    Route::delete('/luminaires/{luminaire}', [LuminaireController::class, 'destroy']);

    // Electrical Boards
    Route::get('/electrical-boards', [ElectricalBoardController::class, 'index']);
    Route::post('/electrical-boards', [ElectricalBoardController::class, 'store']);
    Route::get('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'show']);
    Route::put('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'update']);
    Route::patch('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'update']);
    Route::delete('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'destroy']);

    // Media (photos/documents attached to complexes, terrains, structures, electrical boards)
    Route::post('/{modelType}/{modelId}/media', [FieldOpsMediaController::class, 'store'])
        ->where('modelType', 'complexes|terrains|structures|electrical-boards')
        ->where('modelId', '[0-9]+');
    Route::get('/media/{media}', [FieldOpsMediaController::class, 'show']);
    Route::delete('/media/{media}', [FieldOpsMediaController::class, 'destroy']);
});
