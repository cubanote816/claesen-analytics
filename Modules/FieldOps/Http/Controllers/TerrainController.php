<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Resources\TerrainTypeResource;
use Modules\FieldOps\Models\TerrainType;

class TerrainController extends Controller
{
    public function types(): \Illuminate\Http\JsonResponse
    {
        $types = TerrainType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => TerrainTypeResource::collection($types),
        ]);
    }
}
