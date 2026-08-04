<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Support\ElectricalBoardPinCatalog;
use Modules\FieldOps\Support\StructurePinCatalog;
use Modules\FieldOps\Support\TerrainPinCatalog;

/**
 * Exposes the canonical map-pin catalogs (Terrain/Structure/ElectricalBoard) to any
 * authenticated FieldOps consumer — external map clients (Claesen-Sport-updateing today)
 * should render the exact same markers as the Filament backoffice instead of maintaining
 * a parallel, drifting icon set. Static, tenant-agnostic content: no ownership check
 * beyond the route group's own auth:sanctum, unlike ClientPortalInfrastructureController
 * which is scoped to client-portal users only.
 *
 * Terrain entries keep the `${fill}` placeholder in `svg` un-substituted (see
 * TerrainPinCatalog) so a caller can tint the same 19 shapes per-record with each
 * TerrainType's own `pin_color`, exactly like TerrainPinCatalog::svg() does server-side —
 * without needing 19 separate requests. Structure/electrical-board colors are fixed in
 * the catalog itself, so those come back already render-ready.
 */
class PinCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'terrain' => TerrainPinCatalog::definitions(),
                'structure' => StructurePinCatalog::definitions(),
                'structure_fallback_svg' => StructurePinCatalog::fallbackSvg(),
                'electrical_board_svg' => ElectricalBoardPinCatalog::svg(),
            ],
        ]);
    }
}
