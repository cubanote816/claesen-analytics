<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Resources\ComplexResource;
use Modules\FieldOps\Models\Complex;

class ComplexController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $complexes = Complex::with('client', 'createdBy')->orderBy('name')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => ComplexResource::collection($complexes),
        ]);
    }

    public function show(Complex $complex): \Illuminate\Http\JsonResponse
    {
        $complex->load('client', 'createdBy', 'terrains.terrainType', 'terrains.structures.structureType');

        return response()->json([
            'success' => true,
            'data'    => new ComplexResource($complex),
        ]);
    }
}
