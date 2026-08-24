<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\FieldOps\Http\Requests\StoreLuminaireFrameTypeFromGeneratedRequest;
use Modules\FieldOps\Http\Requests\StoreLuminaireTypeFromSuggestionRequest;
use Modules\FieldOps\Http\Resources\AccessTypeResource;
use Modules\FieldOps\Http\Resources\ElectricalBoardTypeResource;
use Modules\FieldOps\Http\Resources\LuminaireFrameTypeResource;
use Modules\FieldOps\Http\Resources\LuminaireSubgroupResource;
use Modules\FieldOps\Http\Resources\LuminaireTypeResource;
use Modules\FieldOps\Http\Resources\SafetyTypeResource;
use Modules\FieldOps\Http\Resources\StructureTypeResource;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\StructureType;

class CatalogController extends Controller
{
    public function structureTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(StructureTypeResource::collection(StructureType::orderBy('id')->get()));
    }

    public function accessTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(AccessTypeResource::collection(AccessType::orderBy('id')->get()));
    }

    public function safetyTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(SafetyTypeResource::collection(SafetyType::orderBy('id')->get()));
    }

    public function electricalBoardTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(ElectricalBoardTypeResource::collection(ElectricalBoardType::orderBy('id')->get()));
    }

    public function luminaireFrameTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(LuminaireFrameTypeResource::collection(LuminaireFrameType::orderBy('id')->get()));
    }

    public function luminaireTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(LuminaireTypeResource::collection(LuminaireType::orderBy('id')->get()));
    }

    public function luminaireSubgroups(): \Illuminate\Http\JsonResponse
    {
        return $this->respond(LuminaireSubgroupResource::collection(LuminaireSubgroup::orderBy('id')->get()));
    }

    /**
     * Crea un LuminaireFrameType "personalizado" a partir de una foto tomada por el usuario
     * (Claesen-Sport/CameraCapture) — a diferencia del catalogo fijo (created_by_user_id null,
     * solo super_admin), estos quedan asociados al usuario que los creo. La columna
     * created_by_user_id ya distinguia este caso desde el diseño original de la tabla.
     */
    public function storeCustomLuminaireFrameType(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('photo')->store('luminaire-frame-types', 'public');

        $frameType = LuminaireFrameType::create([
            'created_by_user_id' => $request->user()->id,
            'name'                => $validated['name'],
            'image'               => Storage::disk('public')->url($path),
        ]);

        return response()->json([
            'success' => true,
            'data'    => new LuminaireFrameTypeResource($frameType),
        ], 201);
    }

    /**
     * CLA-409 (CLA-390 Fase 3) — creates a LuminaireFrameType from an
     * AI-generated catalog-style illustration (GeminiImageGenerationService,
     * technician-confirmed) instead of a raw uploaded photo. Marked
     * source=ai_generated/verified_by_user_id=null so a super_admin can
     * review it later from Filament — same governance pattern already used
     * for storeLuminaireTypeFromSuggestion() below (CLA-389).
     */
    public function storeGeneratedLuminaireFrameType(StoreLuminaireFrameTypeFromGeneratedRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $decoded = base64_decode($validated['image_base64'], true);

        if ($decoded === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image data.',
            ], 422);
        }

        $path = 'luminaire-frame-types/'.uniqid('generated_', true).'.png';
        Storage::disk('public')->put($path, $decoded);

        $frameType = LuminaireFrameType::create([
            'created_by_user_id' => $request->user()->id,
            'name'                => $validated['name'],
            'image'               => Storage::disk('public')->url($path),
            'source'              => 'ai_generated',
        ]);

        return response()->json([
            'success' => true,
            'data'    => new LuminaireFrameTypeResource($frameType),
        ], 201);
    }

    /**
     * CLA-389 — creates a LuminaireType (and its LuminaireSubgroup, if the brand
     * doesn't exist yet) from a technician-confirmed out-of-catalog AI suggestion
     * (ClaudeVisionService, candidate.suggested_brand/suggested_model). Same trust
     * level as storeCustomLuminaireFrameType() above — any authenticated FieldOps
     * user, not just super_admin — but marked source=ai_suggestion/verified_by_user_id=null
     * so a super_admin can review it later from Filament. Deduplicated case-insensitively
     * on brand and on name-within-subgroup, so two technicians suggesting the same real
     * product don't create two catalog rows.
     */
    public function storeLuminaireTypeFromSuggestion(StoreLuminaireTypeFromSuggestionRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $subgroup = LuminaireSubgroup::whereRaw('LOWER(brand) = ?', [mb_strtolower($validated['brand'])])->first();

        if (! $subgroup) {
            $subgroup = LuminaireSubgroup::create([
                'created_by_user_id' => $request->user()->id,
                'group_name' => 'LED',
                'brand' => $validated['brand'],
                'source' => 'ai_suggestion',
            ]);
        }

        $type = LuminaireType::where('luminaire_subgroup_id', $subgroup->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['model_name'])])
            ->first();

        if (! $type) {
            $type = LuminaireType::create([
                'created_by_user_id' => $request->user()->id,
                'luminaire_subgroup_id' => $subgroup->id,
                'name' => $validated['model_name'],
                'source' => 'ai_suggestion',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new LuminaireTypeResource($type),
        ], 201);
    }

    private function respond($collection): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $collection,
        ]);
    }
}
