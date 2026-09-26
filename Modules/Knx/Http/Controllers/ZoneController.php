<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\ZoneResource;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;
use Modules\Knx\Services\KantoorAuthService;
use Modules\Knx\Services\ZoneService;

/**
 * Zonas y preparación de obra (§4.10, contrato completo en BACKEND-API-ZONES.md).
 */
class ZoneController extends Controller
{
    /** @return list<string> */
    private function eagerLoads(): array
    {
        return ['project', 'checks.updatedBy'];
    }

    public function index(Request $request): array
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'nullable', 'string'],
        ]);

        $projectCode = $validated['project'] ?? null;

        $zones = KnxZone::query()
            ->with($this->eagerLoads())
            ->when($projectCode !== null && trim($projectCode) !== '', fn ($query) => $query->whereHas(
                'project',
                fn ($inner) => $inner->where('code', $projectCode),
            ))
            ->orderBy('id')
            ->get();

        return ZoneResource::list($zones, $request);
    }

    public function show(Request $request, string $id): ZoneResource
    {
        return new ZoneResource(
            KnxZone::query()->with($this->eagerLoads())->findOrFail($id),
        );
    }

    /**
     * Answers the zone **already recomputed**: the caller never has to guess what
     * a check change did to the derived status.
     */
    public function updateCheck(
        Request $request,
        string $id,
        string $key,
        ZoneService $zones,
        KantoorAuthService $auth,
    ): ZoneResource {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(KnxZoneCheck::STATUSES)],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $zone = KnxZone::query()->with($this->eagerLoads())->findOrFail($id);
        $user = $request->user();

        $updated = $zones->updateCheck(
            $zone,
            $key,
            $validated['status'],
            $validated['note'] ?? null,
            $user === null ? null : $auth->authorize($user),
        );

        return new ZoneResource($updated);
    }
}
