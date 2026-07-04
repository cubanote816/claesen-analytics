<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreFoClientRequest;
use Modules\FieldOps\Http\Requests\UpdateFoClientRequest;
use Modules\FieldOps\Http\Resources\FoClientResource;
use Modules\FieldOps\Models\FoClient;

class FoClientController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $clients = FoClient::withCount('complexes')->orderBy('name')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => FoClientResource::collection($clients),
        ]);
    }

    public function show(FoClient $foClient): \Illuminate\Http\JsonResponse
    {
        $foClient->loadCount('complexes');

        return response()->json([
            'success' => true,
            'data'    => new FoClientResource($foClient),
        ]);
    }

    public function store(StoreFoClientRequest $request): \Illuminate\Http\JsonResponse
    {
        $client = FoClient::create($request->validated());
        $client->loadCount('complexes');

        return response()->json([
            'success' => true,
            'data'    => new FoClientResource($client),
        ], 201);
    }

    public function update(UpdateFoClientRequest $request, FoClient $foClient): \Illuminate\Http\JsonResponse
    {
        $foClient->update($request->validated());
        $foClient->loadCount('complexes');

        return response()->json([
            'success' => true,
            'data'    => new FoClientResource($foClient),
        ]);
    }

    public function destroy(FoClient $foClient): \Illuminate\Http\Response
    {
        $foClient->delete();

        return response()->noContent();
    }
}
