<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\ConflictResource;
use Modules\Knx\Http\Resources\DeviceResource;
use Modules\Knx\Http\Resources\FunctionSpecResource;
use Modules\Knx\Http\Resources\RoomResource;
use Modules\Knx\Http\Resources\ZoneResource;
use Modules\Knx\Models\KnxBoardLink;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxRoomAlias;
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
     * room, in one payload instead of six calls.
     *
     * `intents` and `photos` are empty for now: they are real sections of the contract
     * (KNX-55 / KNX-26) whose tables do not exist yet. The shape is served now so the
     * front can build the screen, and the arrays fill in when those slices land.
     */
    public function sheet(Request $request, string $code, string $id): array
    {
        $project = $this->project($code);

        // The room must belong to THIS project: the same id in another project is a 404.
        $room = KnxProjectRoom::query()
            ->where('project_id', $project->getKey())
            ->whereKey($id)
            ->with(['aliases', 'zone.checks.updatedBy'])
            ->firstOrFail();

        $conflictAddresses = KnxConflict::query()
            ->where('project_id', $project->getKey())
            ->open()
            ->pluck('address')
            ->all();

        $devices = KnxDevice::query()
            ->where('room_id', $room->getKey())
            ->with(['room', 'board', 'registeredBy'])
            ->orderBy('address')
            ->get()
            ->map(fn (KnxDevice $device): array => (new DeviceResource($device, $conflictAddresses))->resolve($request))
            ->all();

        // The group addresses that control this room, across the project's boards.
        $boardLinks = KnxBoardLink::query()
            ->where('room_id', $room->getKey())
            ->whereHas('module.board', fn ($query) => $query->where('project_id', $project->getKey()))
            ->with('module.board')
            ->orderBy('id')
            ->get()
            ->map(fn (KnxBoardLink $link): array => [
                'boardId' => (string) ($link->module->board->slug ?? $link->module->board->code),
                'boardName' => (string) ($link->module->board->name ?? $link->module->board->code),
                'channel' => $link->channel,
                'roomId' => (string) $link->room_id,
                'room' => $link->room,
                'object' => $link->object,
                'role' => $link->role,
                'ga' => $link->ga,
                'gaName' => $link->ga_name,
                'dpt' => $link->dpt,
            ])
            ->all();

        // Functional specs hang off the zone, and zona = habitación.
        $functions = $room->zone === null
            ? []
            : KnxFunctionSpec::query()
                ->where('zone_id', $room->zone->getKey())
                ->with(['project', 'zone'])
                ->orderBy('id')
                ->get()
                ->map(fn (KnxFunctionSpec $spec): array => FunctionSpecResource::make($spec)->resolve($request))
                ->all();

        // An issue anchors to a device; the room is the device's room.
        $issues = KnxConflict::query()
            ->where('project_id', $project->getKey())
            ->whereHas('device', fn ($query) => $query->where('room_id', $room->getKey()))
            ->ordered()
            ->with(['project', 'device', 'reportedBy', 'logs'])
            ->get()
            ->map(fn (KnxConflict $conflict): array => (new ConflictResource($conflict))->resolve($request))
            ->all();

        return [
            'room' => RoomResource::make($room)->resolve($request),
            'zone' => $room->zone === null ? null : ZoneResource::make($room->zone)->resolve($request),
            'devices' => $devices,
            'boardLinks' => $boardLinks,
            'intents' => [],
            'functions' => $functions,
            'issues' => $issues,
            'photos' => [],
        ];
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
