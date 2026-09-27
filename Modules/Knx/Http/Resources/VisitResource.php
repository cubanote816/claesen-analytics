<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxVisit;

/**
 * A closure as the office reads it (V11.e, CLA-609).
 *
 * One row per closure, newest first, with the four lists in the order the technician
 * wrote them. `minutes` is nullable on purpose: a closure whose time was not reported
 * is a real closure, and the office's `hours` report shows it with empty time rather
 * than pretending it never happened.
 *
 * @property-read KnxVisit $resource
 */
class VisitResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $visit = $this->resource;

        return [
            'id' => (string) $visit->getKey(),
            'projectCode' => $visit->project?->code,
            'type' => $visit->type,
            // The room the office knows, or the name the technician read on site.
            'room' => $visit->location(),
            'workDone' => $visit->work_done,
            'minutes' => $visit->minutes,
            'signedBy' => $visit->signed_by,
            'capturedAt' => $visit->captured_at?->toIso8601String(),
            'closedBy' => $visit->closedBy?->shortName(),
            'pending' => $visit->itemsOfKind(KnxVisit::ITEM_PENDING),
            'reservations' => $visit->itemsOfKind(KnxVisit::ITEM_RESERVATION),
            'verifiedFunctions' => $visit->itemsOfKind(KnxVisit::ITEM_VERIFIED_FUNCTION),
            'documents' => $visit->itemsOfKind(KnxVisit::ITEM_DOCUMENT),
        ];
    }
}
