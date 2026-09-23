<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\UpdateComplexRequest;
use Modules\FieldOps\Http\Resources\ComplexResource;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Services\ComplexCountsService;
use Modules\FieldOps\Services\FieldOpsTenantService;

class ComplexController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $complexes = app(FieldOpsTenantService::class)
            ->scopeForUser(Complex::query(), $request->user(), Complex::class)
            ->with('client', 'createdBy', 'media')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->string('search'));

                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%")
                        ->orWhere('street', 'like', "%{$term}%")
                        ->orWhere('zipcode', 'like', "%{$term}%")
                        ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderBy('name')
            ->paginate(50);

        $this->attachCounts($complexes->getCollection(), $request);

        return response()->json([
            'success' => true,
            'data' => ComplexResource::collection($complexes),
        ]);
    }

    public function show(Request $request, Complex $complex): \Illuminate\Http\JsonResponse
    {
        $complex->load('client', 'createdBy', 'media', 'terrains.terrainType', 'terrains.structures.structureType');
        $this->attachCounts(collect([$complex]), $request);

        return response()->json([
            'success' => true,
            'data' => new ComplexResource($complex),
        ]);
    }

    public function update(UpdateComplexRequest $request, Complex $complex): \Illuminate\Http\JsonResponse
    {
        $complex->update($request->validated());

        $complex->load('client', 'createdBy', 'media');

        return response()->json([
            'success' => true,
            'data' => new ComplexResource($complex),
        ]);
    }

    public function destroy(Complex $complex): \Illuminate\Http\Response
    {
        // Soft delete only — fo_terrains.complex_id has cascadeOnDelete at DB level,
        // but SoftDeletes does not trigger it. forceDelete() would cascade to terrains.
        $complex->delete();

        return response()->noContent();
    }

    /** Conteos para la UI (CLA-594): una tanda de consultas agrupadas por página, no por fila. */
    private function attachCounts(\Illuminate\Support\Collection $complexes, Request $request): void
    {
        $counts = app(ComplexCountsService::class)->forComplexes($complexes->pluck('id')->all(), $request->user());

        foreach ($complexes as $complex) {
            $complex->setAttribute('counts', $counts[$complex->id]);
        }
    }
}
