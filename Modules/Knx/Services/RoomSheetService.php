<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Http\Request;
use Modules\Knx\Http\Resources\ConflictResource;
use Modules\Knx\Http\Resources\DeviceResource;
use Modules\Knx\Http\Resources\FunctionSpecResource;
use Modules\Knx\Http\Resources\IntentResource;
use Modules\Knx\Http\Resources\RoomResource;
use Modules\Knx\Http\Resources\ZoneResource;
use Modules\Knx\Models\KnxBoardLink;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxIntent;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;

/**
 * The room sheet: everything the office (and the field) shows for one room (KNX-46).
 *
 * It lives in a service because two readers need the exact same payload: the office's
 * `GET /projects/{code}/rooms/{id}/sheet` and the field's cached
 * `GET /field/projects/{code}/rooms`. Building it twice is how the two drift.
 *
 * `photos` is a real section of the contract (KNX-26) whose table does not exist yet;
 * it travels as an empty array so the shape is stable. `intents` is real since KNX-55.
 */
class RoomSheetService
{
    public function __construct(private readonly IntentService $intents) {}

    /**
     * @return array<string, mixed>
     */
    public function forRoom(KnxProjectRoom $room, Request $request): array
    {
        $project = $room->project;

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

        $functions = $room->zone === null
            ? []
            : KnxFunctionSpec::query()
                ->where('zone_id', $room->zone->getKey())
                ->with(['project', 'zone'])
                ->orderBy('id')
                ->get()
                ->map(fn (KnxFunctionSpec $spec): array => FunctionSpecResource::make($spec)->resolve($request))
                ->all();

        $issues = KnxConflict::query()
            ->where('project_id', $project->getKey())
            ->whereHas('device', fn ($query) => $query->where('room_id', $room->getKey()))
            ->ordered()
            ->with(['project', 'device', 'reportedBy', 'logs'])
            ->get()
            ->map(fn (KnxConflict $conflict): array => (new ConflictResource($conflict))->resolve($request))
            ->all();

        $intents = $this->intents->forRoom($room)
            ->map(fn (KnxIntent $intent): array => (new IntentResource($intent))->resolve($request))
            ->all();

        return [
            'room' => RoomResource::make($room)->resolve($request),
            'zone' => $room->zone === null ? null : ZoneResource::make($room->zone)->resolve($request),
            'devices' => $devices,
            'boardLinks' => $boardLinks,
            'intents' => $intents,
            'functions' => $functions,
            'issues' => $issues,
            'photos' => [],
        ];
    }

    /**
     * Every room of a project as a sheet — what the field app caches for no-signal work.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forProject(KnxProject $project, Request $request): array
    {
        return KnxProjectRoom::query()
            ->where('project_id', $project->getKey())
            ->with(['aliases', 'zone.checks.updatedBy', 'project'])
            ->orderBy('id')
            ->get()
            ->map(fn (KnxProjectRoom $room): array => $this->forRoom($room, $request))
            ->values()
            ->all();
    }
}
