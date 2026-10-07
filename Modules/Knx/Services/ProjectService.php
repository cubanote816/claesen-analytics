<?php

namespace Modules\Knx\Services;

use Illuminate\Support\Facades\DB;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;

/**
 * Creating a project (K2, docs/BACKEND-API.md §4.3).
 *
 * The rooms are part of the creation, not a follow-up edit: the field app's
 * plan screen reads them, so a project without its rooms is not usable from
 * site. One transaction, so a half-created project can never exist.
 */
class ProjectService
{
    /**
     * `status`, the device counters and `photos` keep their column defaults:
     * the caller starts an empty project, it does not invent history. The
     * tenant trait fills `organization_id` from the session's organization.
     *
     * @param  array<string, mixed>  $attributes  the validated payload (without `rooms`)
     * @param  array<int, array{name: string, floor: string|null}>  $rooms
     */
    public function create(array $attributes, array $rooms = []): KnxProject
    {
        return DB::transaction(function () use ($attributes, $rooms): KnxProject {
            $project = KnxProject::create($attributes);

            foreach ($rooms as $room) {
                KnxProjectRoom::create([
                    'project_id' => $project->id,
                    'name' => $room['name'],
                    'floor' => $room['floor'] ?? null,
                ]);
            }

            // Re-read the row: the in-memory model does not see the column
            // defaults (`status`, the counters, `photos`) the database filled in,
            // and the response is the read shape the office already parses.
            return $project->refresh();
        });
    }
}
