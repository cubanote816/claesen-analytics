<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Database\Seeders\PlaceholderLuminaireTypeSeeder;
use Modules\FieldOps\Http\Requests\StoreFrameTypeVisionSuggestionRequest;
use Modules\FieldOps\Http\Requests\StoreLuminaireDetectionVisionSuggestionRequest;
use Modules\FieldOps\Http\Requests\StoreLuminaireVisionSuggestionRequest;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;
use Modules\Intelligence\Services\ClaudeVisionService;

class LuminaireVisionController extends Controller
{
    /**
     * Compare a photo of a frame against the internal luminaire catalog and
     * return identification candidates. Read-only — never creates or edits
     * a luminaire; the technician must confirm before anything is persisted.
     */
    public function suggest(StoreLuminaireVisionSuggestionRequest $request, LuminaireFrame $frame, ClaudeVisionService $vision): \Illuminate\Http\JsonResponse
    {
        $photo = $request->file('photo');

        $catalog = LuminaireType::with('subgroup')
            ->orderBy('id')
            ->get()
            ->map(fn (LuminaireType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'product_family' => $type->product_family,
                'model_reference' => $type->model_reference,
                'typical_application' => $type->typical_application,
                'brand' => $type->subgroup?->brand,
                'group_name' => $type->subgroup?->group_name,
            ])
            ->all();

        $result = $vision->identifyLuminaires(
            base64_encode($photo->get()),
            $photo->getMimeType(),
            $catalog
        );

        return response()->json([
            'success' => true,
            'data' => [
                'luminaire_frame_id' => $frame->id,
                'status' => $result['status'],
                'candidates' => $result['candidates'],
            ],
        ]);
    }

    /**
     * CLA-391 (CLA-390 Fase 2) — detect every luminaire fixture visible in a
     * photo of an already-mounted frame, with an approximate position and a
     * best-effort type match per detection. Read-only — never creates or
     * edits a luminaire; the technician confirms which detections to turn
     * into real installations (LuminaireController::store, one call per
     * accepted detection, reusing the existing single-luminaire creation
     * path rather than a new bulk endpoint).
     */
    public function detectLuminaires(StoreLuminaireDetectionVisionSuggestionRequest $request, LuminaireFrame $frame, ClaudeVisionService $vision): \Illuminate\Http\JsonResponse
    {
        $photo = $request->file('photo');

        $catalog = LuminaireType::with('subgroup')
            ->orderBy('id')
            ->get()
            ->map(fn (LuminaireType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'product_family' => $type->product_family,
                'model_reference' => $type->model_reference,
                'typical_application' => $type->typical_application,
                'brand' => $type->subgroup?->brand,
                'group_name' => $type->subgroup?->group_name,
            ])
            ->all();

        $result = $vision->detectLuminairesInFrame(
            base64_encode($photo->get()),
            $photo->getMimeType(),
            $catalog
        );

        return response()->json([
            'success' => true,
            'data' => [
                'luminaire_frame_id' => $frame->id,
                'status' => $result['status'],
                'detections' => $result['detections'],
                'placeholder' => PlaceholderLuminaireTypeSeeder::resolveIds(),
            ],
        ]);
    }

    /**
     * CLA-390 — compare a photo of a physical frame against the internal
     * LuminaireFrameType catalog, before any LuminaireFrame instance exists.
     * Read-only — never creates or edits a frame type; the technician confirms
     * (reusing the matched type) or falls back to the existing "create custom
     * frame type from this photo" flow when nothing matches.
     */
    public function suggestFrameType(StoreFrameTypeVisionSuggestionRequest $request, ClaudeVisionService $vision): \Illuminate\Http\JsonResponse
    {
        $photo = $request->file('photo');

        $catalog = LuminaireFrameType::orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (LuminaireFrameType $type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])
            ->all();

        $result = $vision->identifyFrameType(
            base64_encode($photo->get()),
            $photo->getMimeType(),
            $catalog
        );

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $result['status'],
                'candidates' => $result['candidates'],
            ],
        ]);
    }
}
