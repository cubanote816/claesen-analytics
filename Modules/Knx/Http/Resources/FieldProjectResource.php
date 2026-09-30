<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxProject;

/**
 * `Project` of the Veld contract: what the app needs to register a device with no
 * connection — the spaces to choose from, the boards to hang it on and the types
 * it is allowed to offer.
 *
 * `Room.floor` and `Board.name` are strings in that contract and both are nullable
 * in the schema, so an unknown one is an empty string here, the same way
 * `FieldTodayJobResource` handles a project without zones.
 *
 * @property-read KnxProject $resource
 */
class FieldProjectResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $project = $this->resource;
        $client = $project->client;

        return [
            'code' => $project->code,
            'name' => $project->name,
            // The relation is non-nullable in the schema, so this cannot be missing.
            'clientName' => $client->name,
            // The technician rings the client from site and the card shows where the
            // job is; all three live on `knx_clients` already. Strings in the contract,
            // so an unknown one is an empty string rather than a missing key.
            'clientAddress' => $client->address ?? '',
            'clientContact' => $client->contact ?? '',
            'clientPhone' => $client->phone ?? '',
            'city' => $project->city ?? '',
            // The four numbers of the office's project header, from the same source
            // (`ProjectStatsResource`): the columns the office already maintains plus
            // the live conflict count. Recounting them from the device list would be a
            // second definition that can disagree with the office's own.
            'devicesPlanned' => $project->devices_planned,
            'devicesDone' => $project->devices_done,
            'photos' => $project->photos,
            'openConflicts' => $project->openConflicts()->count(),
            'floors' => $this->floors(),
            'rooms' => $project->rooms
                ->map(fn ($room): array => [
                    'id' => (string) $room->getKey(),
                    'name' => $room->name,
                    'floor' => $room->floor ?? '',
                ])
                ->values()
                ->all(),
            'boards' => $project->boards
                ->map(fn ($board): array => [
                    'code' => $board->code,
                    'name' => $board->name ?? '',
                ])
                ->values()
                ->all(),
            'deviceTypes' => $this->deviceTypes(),
        ];
    }

    /**
     * The floor picker's labels, in the order the rooms were modelled.
     *
     * Derived from the rooms rather than stored: a floor is only ever "a level some
     * room is on", and the app filters rooms by it.
     *
     * @return list<string>
     */
    private function floors(): array
    {
        return $this->resource->rooms
            ->pluck('floor')
            ->filter(fn (?string $floor): bool => $floor !== null && $floor !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The device types this project actually uses, in insertion order.
     *
     * There is no catalogue table in this domain, so deriving the list from the
     * project's own devices is the only answer that is not invented. The declared
     * consequence: a project with no devices answers `[]` and the app's type picker
     * has nothing to offer — a catalogue would be a new office-side concept.
     *
     * @return list<string>
     */
    private function deviceTypes(): array
    {
        return $this->resource->devices()
            ->orderBy('id')
            ->pluck('type')
            ->unique()
            ->values()
            ->all();
    }
}
