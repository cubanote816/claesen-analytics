<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreElectricalBoardRequest;
use Modules\FieldOps\Http\Requests\UpdateElectricalBoardRequest;
use Modules\FieldOps\Http\Resources\ElectricalBoardResource;
use Modules\FieldOps\Models\ElectricalBoard;

class ElectricalBoardController extends Controller
{
    private const RELATIONS = ['electricalBoardType', 'complexes', 'terrains', 'structures', 'createdBy', 'media'];

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $boards = ElectricalBoard::with(self::RELATIONS)->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => ElectricalBoardResource::collection($boards),
        ]);
    }

    public function show(ElectricalBoard $electricalBoard): \Illuminate\Http\JsonResponse
    {
        $electricalBoard->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new ElectricalBoardResource($electricalBoard),
        ]);
    }

    public function store(StoreElectricalBoardRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $pivotIds  = collect($validated)->only(['complex_ids', 'terrain_ids', 'structure_ids']);
        $boardData = array_merge(
            collect($validated)->except(['complex_ids', 'terrain_ids', 'structure_ids'])->all(),
            ['created_by_user_id' => $request->user()->id],
        );

        $board = ElectricalBoard::create($boardData);

        if ($pivotIds->get('complex_ids') !== null) {
            $board->complexes()->attach($pivotIds->get('complex_ids'));
        }
        if ($pivotIds->get('terrain_ids') !== null) {
            $board->terrains()->attach($pivotIds->get('terrain_ids'));
        }
        if ($pivotIds->get('structure_ids') !== null) {
            $board->structures()->attach($pivotIds->get('structure_ids'));
        }

        $board->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new ElectricalBoardResource($board),
        ], 201);
    }

    public function update(UpdateElectricalBoardRequest $request, ElectricalBoard $electricalBoard): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $boardData = collect($validated)->except(['complex_ids', 'terrain_ids', 'structure_ids'])->all();

        if (isset($boardData['location_description'])) {
            $boardData['location_description'] = array_merge(
                $electricalBoard->getTranslations('location_description'),
                $boardData['location_description'],
            );
        }

        $electricalBoard->update($boardData);

        // Three distinct cases per relation — must check hasKey, not truthiness:
        // absent → leave pivot untouched | null → detach all | array → sync
        if ($request->has('complex_ids')) {
            $electricalBoard->complexes()->sync($validated['complex_ids'] ?? []);
        }
        if ($request->has('terrain_ids')) {
            $electricalBoard->terrains()->sync($validated['terrain_ids'] ?? []);
        }
        if ($request->has('structure_ids')) {
            $electricalBoard->structures()->sync($validated['structure_ids'] ?? []);
        }

        $electricalBoard->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new ElectricalBoardResource($electricalBoard),
        ]);
    }

    public function destroy(ElectricalBoard $electricalBoard): \Illuminate\Http\Response
    {
        // Soft delete only — all 3 pivot tables have cascadeOnDelete at DB level,
        // but SoftDeletes does not trigger it. forceDelete() would remove rows in
        // fo_complex_electrical_board, fo_electrical_board_terrain and
        // fo_electrical_board_structure.
        $electricalBoard->delete();

        return response()->noContent();
    }
}
