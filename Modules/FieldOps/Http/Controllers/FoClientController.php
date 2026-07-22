<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Resources\FoClientResource;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Services\FieldOpsTenantService;

class FoClientController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $query = app(FieldOpsTenantService::class)
            ->scopeForUser(FoClient::query(), request()->user(), FoClient::class);
        $clients = $query->withCount('complexes')->orderBy('name')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => FoClientResource::collection($clients),
        ]);
    }

    public function show(FoClient $foClient): \Illuminate\Http\JsonResponse
    {
        $foClient->loadCount('complexes');

        return response()->json([
            'success' => true,
            'data' => new FoClientResource($foClient),
        ]);
    }
}
