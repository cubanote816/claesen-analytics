<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxFunctionSpec;

/**
 * `FunctionSpec` of BACKEND-API-ZONES.md §2 — what a room must DO, agreed before
 * programming.
 *
 * The list-shaped fields (`triggers`, `groupAddresses`, `dpts`…) are stored as JSON
 * and come back as arrays; `zoneName` is derived, and `author`/`approvedBy` are the
 * short display names the contract uses everywhere.
 *
 * @property-read KnxFunctionSpec $resource
 */
class FunctionSpecResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $spec = $this->resource;

        return [
            'id' => (string) $spec->getKey(),
            'projectCode' => $spec->project?->code,
            'zoneId' => $spec->zone_id === null ? null : (string) $spec->zone_id,
            'zoneName' => $spec->zone?->name,
            'name' => $spec->name,
            'objective' => $spec->objective,
            'triggers' => $spec->triggers ?? [],
            'conditions' => $spec->conditions ?? [],
            'manualControls' => $spec->manual_controls ?? [],
            'automations' => $spec->automations ?? [],
            'timings' => $spec->timings,
            'priorities' => $spec->priorities,
            'failureBehaviour' => $spec->failure_behaviour,
            'dependencies' => $spec->dependencies ?? [],
            'acceptanceCriteria' => $spec->acceptance_criteria ?? [],
            'groupAddresses' => $spec->group_addresses ?? [],
            'dpts' => $spec->dpts ?? [],
            'knxObjects' => $spec->knx_objects ?? [],
            'status' => $spec->status,
            'version' => $spec->version,
            'author' => $spec->author?->shortName(),
            'approvedBy' => $spec->approvedBy?->shortName(),
            'approvedAt' => $spec->approved_at?->toIso8601ZuluString(),
            'updatedAt' => $spec->updated_at->toIso8601ZuluString(),
        ];
    }
}
