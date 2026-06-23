<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreStructureRequest;
use Modules\FieldOps\Http\Requests\UpdateStructureRequest;
use Modules\FieldOps\Http\Resources\StructureResource;
use Modules\FieldOps\Models\Structure;

class StructureController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $structures = Structure::with('structureType', 'terrains', 'createdBy')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => StructureResource::collection($structures),
        ]);
    }

    public function show(Structure $structure): \Illuminate\Http\JsonResponse
    {
        $structure->load('structureType', 'terrains', 'createdBy', 'luminaireFrames.luminaires.luminaireType');

        return response()->json([
            'success' => true,
            'data'    => new StructureResource($structure),
        ]);
    }

    public function store(StoreStructureRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated   = $request->validated();
        $terrainIds  = $validated['terrain_ids'] ?? null;
        $structureData = array_merge(
            collect($validated)->except('terrain_ids')->all(),
            ['created_by_user_id' => $request->user()->id],
        );

        $structure = Structure::create($structureData);

        if ($terrainIds !== null) {
            $structure->terrains()->attach($terrainIds);
        }

        $structure->load('structureType', 'terrains', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new StructureResource($structure),
        ], 201);
    }

    public function update(UpdateStructureRequest $request, Structure $structure): \Illuminate\Http\JsonResponse
    {
        $validated  = $request->validated();
        $structureData = collect($validated)->except('terrain_ids')->all();

        if (isset($structureData['info'])) {
            $structureData['info'] = array_merge(
                $structure->getTranslations('info'),
                $structureData['info'],
            );
        }

        $structure->update($structureData);

        // Three distinct cases — must check hasKey, not truthiness:
        // absent  → $request->has() is false  → leave pivot untouched
        // null    → explicit null sent         → detach all
        // array   → sync to the given IDs
        if ($request->has('terrain_ids')) {
            $structure->terrains()->sync($validated['terrain_ids'] ?? []);
        }

        $structure->load('structureType', 'terrains', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new StructureResource($structure),
        ]);
    }

    public function destroy(Structure $structure): \Illuminate\Http\Response
    {
        // Soft delete only — fo_structure_terrain.structure_id and
        // fo_luminaire_frame_structure.structure_id both have cascadeOnDelete at DB
        // level, but SoftDeletes does not trigger it. forceDelete() would remove
        // rows in both pivot tables.
        $structure->delete();

        return response()->noContent();
    }
}
