<?php

use Illuminate\Support\Facades\Route;
use Modules\FieldOps\Http\Controllers\CatalogController;
use Modules\FieldOps\Http\Controllers\ClientContactController;
use Modules\FieldOps\Http\Controllers\ClientPortalInfrastructureController;
use Modules\FieldOps\Http\Controllers\ComplexController;
use Modules\FieldOps\Http\Controllers\ElectricalBoardController;
use Modules\FieldOps\Http\Controllers\FieldOpsMediaController;
use Modules\FieldOps\Http\Controllers\FieldOpsNotificationController;
use Modules\FieldOps\Http\Controllers\FoClientController;
use Modules\FieldOps\Http\Controllers\LuminaireController;
use Modules\FieldOps\Http\Controllers\LuminaireFrameController;
use Modules\FieldOps\Http\Controllers\MaintenanceRecordController;
use Modules\FieldOps\Http\Controllers\MaintenanceRequestController;
use Modules\FieldOps\Http\Controllers\MaintenanceWorkOrderController;
use Modules\FieldOps\Http\Controllers\StructureController;
use Modules\FieldOps\Http\Controllers\TerrainController;
use Modules\FieldOps\Http\Middleware\EnforceFieldOpsTenantAccess;

Route::middleware(['auth:sanctum', \Modules\Core\Http\Middleware\SetLocaleFromHeader::class, EnforceFieldOpsTenantAccess::class])
    ->prefix('v1/fieldops')->group(function () {
        // External client portal: a deliberately read-only, reduced topology projection.
        Route::get('/client-portal/infrastructure', [ClientPortalInfrastructureController::class, 'index']);

        // Clients
        Route::get('/clients', [FoClientController::class, 'index']);
        Route::get('/clients/{foClient}', [FoClientController::class, 'show']);
        Route::post('/clients/{foClient}/contacts/invitations', [ClientContactController::class, 'invite']);

        // Complexes
        Route::get('/complexes', [ComplexController::class, 'index']);
        Route::get('/complexes/{complex}', [ComplexController::class, 'show']);
        Route::put('/complexes/{complex}', [ComplexController::class, 'update']);
        Route::patch('/complexes/{complex}', [ComplexController::class, 'update']);
        Route::delete('/complexes/{complex}', [ComplexController::class, 'destroy']);

        // Terrain catalogs
        Route::get('/terrain-types', [TerrainController::class, 'types']);

        // Terrains
        Route::get('/terrains', [TerrainController::class, 'index']);
        Route::post('/terrains', [TerrainController::class, 'store']);
        Route::get('/terrains/count', [TerrainController::class, 'count']);
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
        Route::post('/luminaires/{luminaire}/replacement', [LuminaireController::class, 'replace']);
        Route::delete('/luminaires/{luminaire}', [LuminaireController::class, 'destroy']);

        // Electrical Boards
        Route::get('/electrical-boards', [ElectricalBoardController::class, 'index']);
        Route::post('/electrical-boards', [ElectricalBoardController::class, 'store']);
        Route::get('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'show']);
        Route::put('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'update']);
        Route::patch('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'update']);
        Route::delete('/electrical-boards/{electricalBoard}', [ElectricalBoardController::class, 'destroy']);

        // Maintenance catalog
        Route::get('/maintenance-types', [MaintenanceRecordController::class, 'types']);
        Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index']);
        Route::post('/maintenance-requests/intake/suggest', [MaintenanceRequestController::class, 'suggestIntake']);
        Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store']);
        Route::get('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show']);
        Route::post('/maintenance-requests/{maintenanceRequest}/convert', [MaintenanceRequestController::class, 'convert']);
        Route::patch('/maintenance-requests/{maintenanceRequest}/respond', [MaintenanceRequestController::class, 'respond']);
        Route::post('/maintenance-requests/{maintenanceRequest}/messages', [MaintenanceRequestController::class, 'message']);
        Route::post('/maintenance-requests/{maintenanceRequest}/attachments', [MaintenanceRequestController::class, 'attachment']);
        Route::post('/maintenance-requests/{maintenanceRequest}/confirm', [MaintenanceRequestController::class, 'confirm']);
        Route::post('/maintenance-requests/{maintenanceRequest}/reopen', [MaintenanceRequestController::class, 'reopen']);
        Route::post('/maintenance-requests/{maintenanceRequest}/cancel', [MaintenanceRequestController::class, 'cancel']);
        Route::get('/maintenance-request-attachments/{media}', [MaintenanceRequestController::class, 'showAttachment']);

        // Operational notifications are scoped to FieldOps and the authenticated user.
        Route::get('/notifications', [FieldOpsNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [FieldOpsNotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [FieldOpsNotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/read', [FieldOpsNotificationController::class, 'markAsRead']);

        // Maintenance records (per equipment)
        Route::get('/luminaires/{luminaire}/maintenance-records', [MaintenanceRecordController::class, 'indexForLuminaire']);
        Route::get('/electrical-boards/{electricalBoard}/maintenance-records', [MaintenanceRecordController::class, 'indexForElectricalBoard']);

        // Maintenance planning and field execution. Creation starts from equipment;
        // completed maintenance records are produced only after backoffice validation.
        Route::post('/luminaires/{luminaire}/maintenance-work-orders', [MaintenanceWorkOrderController::class, 'storeForLuminaire']);
        Route::post('/electrical-boards/{electricalBoard}/maintenance-work-orders', [MaintenanceWorkOrderController::class, 'storeForElectricalBoard']);
        Route::get('/maintenance-work-orders/assigned', [MaintenanceWorkOrderController::class, 'assigned']);
        Route::get('/maintenance-work-orders/{workOrder}', [MaintenanceWorkOrderController::class, 'show']);
        Route::post('/maintenance-work-orders/{workOrder}/start', [MaintenanceWorkOrderController::class, 'start']);
        Route::post('/maintenance-work-orders/{workOrder}/submit', [MaintenanceWorkOrderController::class, 'submit']);
        Route::post('/maintenance-work-orders/{workOrder}/return', [MaintenanceWorkOrderController::class, 'returnForCorrection'])
            ->name('fieldops.work-orders.return');
        Route::post('/maintenance-work-orders/{workOrder}/validate', [MaintenanceWorkOrderController::class, 'validateAndClose'])
            ->name('fieldops.work-orders.validate');
        Route::post('/maintenance-work-orders/{workOrder}/override', [MaintenanceWorkOrderController::class, 'overrideAndClose'])
            ->name('fieldops.work-orders.override');

        // Maintenance records — stats and client-reported must be registered before the {maintenanceRecord} wildcard
        Route::get('/maintenance-records/stats/corrective', [MaintenanceRecordController::class, 'correctiveStats']);
        Route::get('/maintenance-records/client-reported/pending', [MaintenanceRecordController::class, 'pendingClientReported']);
        Route::get('/maintenance-records/client-reported/statistics', [MaintenanceRecordController::class, 'clientReportedStatistics']);

        Route::get('/maintenance-records/{maintenanceRecord}', [MaintenanceRecordController::class, 'show']);

        // Catalogs (dropdown data for create/edit forms — mirrors /terrain-types)
        Route::get('/structure-types', [CatalogController::class, 'structureTypes']);
        Route::get('/access-types', [CatalogController::class, 'accessTypes']);
        Route::get('/safety-types', [CatalogController::class, 'safetyTypes']);
        Route::get('/electrical-board-types', [CatalogController::class, 'electricalBoardTypes']);
        Route::get('/luminaire-frame-types', [CatalogController::class, 'luminaireFrameTypes']);
        Route::post('/luminaire-frame-types/custom', [CatalogController::class, 'storeCustomLuminaireFrameType']);
        Route::get('/luminaire-types', [CatalogController::class, 'luminaireTypes']);
        Route::get('/luminaire-subgroups', [CatalogController::class, 'luminaireSubgroups']);

        // Media (photos/documents attached to complexes, terrains, structures, electrical boards)
        Route::post('/{modelType}/{modelId}/media', [FieldOpsMediaController::class, 'store'])
            ->where('modelType', 'complexes|terrains|structures|electrical-boards')
            ->where('modelId', '[0-9]+');
        Route::get('/media/{media}', [FieldOpsMediaController::class, 'show']);
        Route::delete('/media/{media}', [FieldOpsMediaController::class, 'destroy']);
    });
