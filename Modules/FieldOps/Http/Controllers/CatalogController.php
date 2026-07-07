<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
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

    private function respond($collection): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $collection,
        ]);
    }
}
