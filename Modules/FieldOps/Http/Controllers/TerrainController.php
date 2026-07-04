<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreTerrainRequest;
use Modules\FieldOps\Http\Requests\UpdateTerrainRequest;
use Modules\FieldOps\Http\Resources\TerrainResource;
use Modules\FieldOps\Http\Resources\TerrainTypeResource;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;

class TerrainController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Terrain::with('terrainType', 'createdBy', 'media')->orderBy('id');

        if ($request->filled('complex_id')) {
            $query->where('complex_id', $request->integer('complex_id'));
        }

        return response()->json([
            'success' => true,
            'data'    => TerrainResource::collection($query->paginate(50)),
        ]);
    }

    public function show(Terrain $terrain): \Illuminate\Http\JsonResponse
    {
        $terrain->load('terrainType', 'createdBy', 'media');

        return response()->json([
            'success' => true,
            'data'    => new TerrainResource($terrain),
        ]);
    }

    public function store(StoreTerrainRequest $request): \Illuminate\Http\JsonResponse
    {
        $terrain = Terrain::create(array_merge(
            $request->validated(),
            ['created_by_user_id' => $request->user()->id],
        ));

        $terrain->load('terrainType', 'createdBy', 'media');

        return response()->json([
            'success' => true,
            'data'    => new TerrainResource($terrain),
        ], 201);
    }

    public function update(UpdateTerrainRequest $request, Terrain $terrain): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        // Merge locale-by-locale so a PATCH with only name.nl leaves other locales intact.
        if (isset($data['name'])) {
            $data['name'] = array_merge(
                $terrain->getTranslations('name'),
                $data['name'],
            );
        }

        $terrain->update($data);
        $terrain->load('terrainType', 'createdBy', 'media');

        return response()->json([
            'success' => true,
            'data'    => new TerrainResource($terrain),
        ]);
    }

    public function destroy(Terrain $terrain): \Illuminate\Http\Response
    {
        // Soft delete only — fo_structure_terrain.terrain_id has cascadeOnDelete at DB level,
        // but SoftDeletes does not trigger it. forceDelete() would remove pivot rows.
        $terrain->delete();

        return response()->noContent();
    }

    public function types(): \Illuminate\Http\JsonResponse
    {
        $types = TerrainType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => TerrainTypeResource::collection($types),
        ]);
    }
}
