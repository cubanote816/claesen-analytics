<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreComplexRequest;
use Modules\FieldOps\Http\Requests\UpdateComplexRequest;
use Modules\FieldOps\Http\Resources\ComplexResource;
use Modules\FieldOps\Models\Complex;

class ComplexController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $complexes = Complex::with('client', 'createdBy', 'media')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => ComplexResource::collection($complexes),
        ]);
    }

    public function show(Complex $complex): \Illuminate\Http\JsonResponse
    {
        $complex->load('client', 'createdBy', 'media', 'terrains.terrainType', 'terrains.structures.structureType');

        return response()->json([
            'success' => true,
            'data'    => new ComplexResource($complex),
        ]);
    }

    public function store(StoreComplexRequest $request): \Illuminate\Http\JsonResponse
    {
        $complex = Complex::create(array_merge(
            $request->validated(),
            ['created_by_user_id' => $request->user()->id],
        ));

        $complex->load('client', 'createdBy', 'media');

        return response()->json([
            'success' => true,
            'data'    => new ComplexResource($complex),
        ], 201);
    }

    public function update(UpdateComplexRequest $request, Complex $complex): \Illuminate\Http\JsonResponse
    {
        $complex->update($request->validated());

        $complex->load('client', 'createdBy', 'media');

        return response()->json([
            'success' => true,
            'data'    => new ComplexResource($complex),
        ]);
    }

    public function destroy(Complex $complex): \Illuminate\Http\Response
    {
        // Soft delete only — fo_terrains.complex_id has cascadeOnDelete at DB level,
        // but SoftDeletes does not trigger it. forceDelete() would cascade to terrains.
        $complex->delete();

        return response()->noContent();
    }
}
