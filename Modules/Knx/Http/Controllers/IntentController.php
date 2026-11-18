<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\IntentResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxIntent;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\IntentService;
use Modules\Knx\Support\KnxTenant;

/**
 * La intención por elemento (KNX-55), del lado de oficina.
 *
 * `PUT` es un upsert por la identidad del elemento, así que el front no arrastra un id
 * entre llamadas: el target ES la clave. `GET /intents/{id}/versions` no lleva proyecto
 * en la URL y por eso resuelve por el proyecto **y** la organización.
 */
class IntentController extends Controller
{
    public function __construct(private readonly IntentService $intents) {}

    public function index(Request $request, string $code): array
    {
        $project = $this->project($code);

        $validated = $request->validate([
            'boardId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'slot' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'deviceId' => ['sometimes', 'nullable', 'integer'],
            'roomId' => ['sometimes', 'nullable', 'integer'],
        ]);

        return $this->intents->list($project, $validated)
            ->map(fn (KnxIntent $intent): array => (new IntentResource($intent))->resolve($request))
            ->all();
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $project = $this->project($code);

        /** @var KnxEmployee $author */
        $author = $request->attributes->get('knx_employee');

        $validated = $request->validate([
            'target' => ['required', 'array'],
            'target.type' => ['required', 'string', 'in:module,channel,device'],
            'target.boardId' => ['required_if:target.type,module,channel', 'nullable', 'string', 'max:64'],
            'target.slot' => ['required_if:target.type,module,channel', 'nullable', 'integer', 'min:1'],
            'target.channel' => ['required_if:target.type,channel', 'nullable', 'string', 'max:100'],
            'target.deviceId' => ['required_if:target.type,device', 'nullable', 'integer'],
            'roomId' => ['sometimes', 'nullable', 'integer'],
            'functionId' => ['sometimes', 'nullable', 'integer'],
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $intent = $this->intents->put($project, $author, $validated);

        // Explicit 200: a resource from a freshly created row would answer 201 on its own,
        // and the contract's `PUT` is an upsert that always answers the current intent.
        return response()->json((new IntentResource($intent))->resolve($request));
    }

    public function versions(Request $request, string $id): array
    {
        $intent = KnxIntent::query()
            ->where('organization_id', KnxTenant::organizationId())
            ->whereHas('project', fn ($query) => $query->where('organization_id', KnxTenant::organizationId()))
            ->findOrFail($id);

        return $this->intents->versions($intent)
            ->map(fn (KnxIntent $version): array => (new IntentResource($version))->resolve($request))
            ->all();
    }

    private function project(string $code): KnxProject
    {
        return KnxProject::query()
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();
    }
}
