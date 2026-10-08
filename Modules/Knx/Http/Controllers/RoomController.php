<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\RoomResource;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxRoomAlias;
use Modules\Knx\Services\RoomSheetService;
use Modules\Knx\Services\ZoneService;
use Modules\Knx\Support\KnxTenant;

/**
 * The canonical rooms of a project (KNX-44).
 *
 * The office's room is the canonical one; ETS, the worklist and the plan keep their
 * own names as aliases. Every room is created together with its readiness zone and
 * its eight checks — zona = habitación — so a room can never exist without the screen
 * that tracks its readiness.
 *
 * The id-only route (`PATCH /rooms/{id}`) has no project in the URL, so it resolves the
 * room THROUGH its project and the organization: `knx_project_rooms` has no tenant
 * trait (one tenant, so the trait would only hide "no rows" bugs), and a room of
 * another organization must answer 404, exactly like an unknown one.
 */
class RoomController extends Controller
{
    public function index(Request $request, string $code): array
    {
        $project = $this->project($code);

        $rooms = KnxProjectRoom::query()
            ->where('project_id', $project->getKey())
            ->with(['aliases', 'zone'])
            ->orderBy('id')
            ->get();

        return RoomResource::list($rooms, $request);
    }

    public function store(Request $request, string $code, ZoneService $zones): JsonResponse
    {
        $project = $this->project($code);

        $validated = $this->validatePayload($request);

        $room = DB::transaction(function () use ($project, $validated, $zones): KnxProjectRoom {
            $room = KnxProjectRoom::create([
                'project_id' => $project->getKey(),
                'name' => $validated['name'],
                'floor' => $validated['floor'] ?? null,
            ]);

            $this->syncAliases($room, $validated['aliases'] ?? []);
            $zones->createForRoom($room);

            return $room;
        });

        return response()->json(
            RoomResource::make($room->load(['aliases', 'zone']))->resolve($request),
            201,
        );
    }

    public function update(Request $request, string $id): RoomResource
    {
        $room = $this->resolveRoom($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*.source' => ['required', Rule::in(KnxRoomAlias::SOURCES)],
            'aliases.*.name' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($room, $validated): void {
            if (array_key_exists('name', $validated) || array_key_exists('floor', $validated)) {
                $room->fill([
                    'name' => $validated['name'] ?? $room->name,
                    'floor' => array_key_exists('floor', $validated) ? $validated['floor'] : $room->floor,
                ])->save();
            }

            // `aliases`, when present, REPLACES the set: a rename that leaves the old
            // alias behind would keep resolving the old name to this room forever.
            if (array_key_exists('aliases', $validated)) {
                $room->aliases()->delete();
                $this->syncAliases($room, $validated['aliases']);
            }
        });

        return RoomResource::make($room->refresh()->load(['aliases', 'zone']));
    }

    /**
     * The full room sheet (KNX-46): everything the office shows when someone clicks a
     * room, in one payload instead of six calls. The shape is owned by
     * `RoomSheetService`, which the field endpoint reuses.
     */
    public function sheet(Request $request, string $code, string $id, RoomSheetService $sheets): array
    {
        $project = $this->project($code);

        // The room must belong to THIS project: the same id in another project is a 404.
        $room = KnxProjectRoom::query()
            ->where('project_id', $project->getKey())
            ->whereKey($id)
            ->with(['aliases', 'zone.checks.updatedBy', 'project'])
            ->firstOrFail();

        return $sheets->forRoom($room, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*.source' => ['required', Rule::in(KnxRoomAlias::SOURCES)],
            'aliases.*.name' => ['required', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<int, array{source: string, name: string}>  $aliases
     */
    private function syncAliases(KnxProjectRoom $room, array $aliases): void
    {
        // The unique key is (room, source, name); a payload that repeats a pair would
        // otherwise be a 500 from the index instead of a clean insert.
        $seen = [];

        foreach ($aliases as $alias) {
            $key = $alias['source'].'|'.$alias['name'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $room->aliases()->create([
                'source' => $alias['source'],
                'name' => $alias['name'],
            ]);
        }
    }

    private function project(string $code): KnxProject
    {
        return KnxProject::query()
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();
    }

    private function resolveRoom(string $id): KnxProjectRoom
    {
        return KnxProjectRoom::query()
            ->whereKey($id)
            ->whereHas('project', fn ($query) => $query->where('organization_id', KnxTenant::organizationId()))
            ->firstOrFail();
    }
}
