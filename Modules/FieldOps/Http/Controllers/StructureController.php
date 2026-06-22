<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Resources\StructureResource;
use Modules\FieldOps\Models\Structure;

class StructureController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $structures = Structure::with('structureType', 'terrains')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => StructureResource::collection($structures),
        ]);
    }

    public function show(Structure $structure): \Illuminate\Http\JsonResponse
    {
        $structure->load('structureType', 'terrains', 'luminaireFrames.luminaires.luminaireType');

        return response()->json([
            'success' => true,
            'data'    => new StructureResource($structure),
        ]);
    }
}
