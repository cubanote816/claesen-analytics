<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
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

    private function respond($collection): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $collection,
        ]);
    }
}
