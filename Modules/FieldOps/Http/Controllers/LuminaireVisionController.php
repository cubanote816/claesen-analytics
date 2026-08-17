<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreLuminaireVisionSuggestionRequest;
use Modules\FieldOps\Models\LuminaireFrame;
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
}
