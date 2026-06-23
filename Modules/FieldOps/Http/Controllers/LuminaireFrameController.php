<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreLuminaireFrameRequest;
use Modules\FieldOps\Http\Requests\UpdateLuminaireFrameRequest;
use Modules\FieldOps\Http\Resources\LuminaireFrameResource;
use Modules\FieldOps\Http\Resources\LuminaireResource;
use Modules\FieldOps\Models\LuminaireFrame;

class LuminaireFrameController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        // luminaires intentionally not loaded here — too heavy for list responses
        $frames = LuminaireFrame::with('frameType', 'structures', 'createdBy')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => LuminaireFrameResource::collection($frames),
        ]);
    }

    public function show(LuminaireFrame $frame): \Illuminate\Http\JsonResponse
    {
        $frame->load('frameType', 'structures', 'createdBy', 'luminaires.luminaireType', 'luminaires.subgroup', 'luminaires.createdBy');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireFrameResource($frame),
        ]);
    }

    public function store(StoreLuminaireFrameRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated    = $request->validated();
        $structureIds = $validated['structure_ids'] ?? null;
        $frameData    = array_merge(
            collect($validated)->except('structure_ids')->all(),
            ['created_by_user_id' => $request->user()->id],
        );

        $frame = LuminaireFrame::create($frameData);

        if ($structureIds !== null) {
            $frame->structures()->attach($structureIds);
        }

        $frame->load('frameType', 'structures', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireFrameResource($frame),
        ], 201);
    }

    public function update(UpdateLuminaireFrameRequest $request, LuminaireFrame $frame): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $frame->update(collect($validated)->except('structure_ids')->all());

        // Three explicit cases — $request->has() distinguishes absent from null
        if ($request->has('structure_ids')) {
            $frame->structures()->sync($validated['structure_ids'] ?? []);
        }

        $frame->load('frameType', 'structures', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireFrameResource($frame),
        ]);
    }

    public function destroy(LuminaireFrame $frame): \Illuminate\Http\Response
    {
        // Soft delete only — fo_luminaires.luminaire_frame_id and
        // fo_luminaire_frame_structure.luminaire_frame_id both have cascadeOnDelete
        // at DB level, but SoftDeletes does not trigger them. forceDelete() would
        // remove all child luminaires and pivot rows.
        $frame->delete();

        return response()->noContent();
    }

    public function luminaires(LuminaireFrame $frame): \Illuminate\Http\JsonResponse
    {
        $frame->load('luminaires.luminaireType', 'luminaires.subgroup');

        return response()->json([
            'success' => true,
            'data'    => LuminaireResource::collection($frame->luminaires),
        ]);
    }
}
