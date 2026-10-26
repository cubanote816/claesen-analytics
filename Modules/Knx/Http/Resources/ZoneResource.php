<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;

/**
 * `Zone` of docs/BACKEND-API-ZONES.md §1.2.
 *
 * `status`, `blockingReason` and `blockedBy` are **derived on every read and
 * every write** — that is the whole contract of this slice, and the derivation
 * lives on the model so there is exactly one implementation of it. The front never
 * sends a status and nothing stores one.
 *
 * The checks always come back as the eight keys in display order, even though the
 * database does not guarantee that order.
 *
 * @property-read KnxZone $resource
 */
class ZoneResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $zone = $this->resource;
        $blocker = $zone->blockingCheck();

        return [
            'id' => (string) $zone->getKey(),
            'projectCode' => $zone->project?->code,
            'projectName' => $zone->project?->name,
            'name' => $zone->name,
            'floor' => $zone->floor,
            'status' => $zone->derivedStatus(),
            // From the FIRST failed check: its note is the blocker and its author
            // owns it. The second failure is usually the follow-up action.
            'blockingReason' => $blocker?->note,
            'blockedBy' => $blocker?->updatedBy?->shortName(),
            'nextReviewAt' => $zone->next_review_at?->format('Y-m-d'),
            'checks' => $zone->orderedChecks()
                ->map(static fn (KnxZoneCheck $check): array => [
                    'key' => $check->key,
                    'status' => $check->status,
                    'updatedAt' => $check->updated_at?->toIso8601ZuluString(),
                    'updatedBy' => $check->updatedBy?->shortName(),
                    'note' => $check->note,
                ])
                ->values()
                ->all(),
        ];
    }
}
