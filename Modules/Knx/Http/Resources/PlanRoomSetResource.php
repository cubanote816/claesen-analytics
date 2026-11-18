<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanRoom;

/**
 * The room model of one plan page (KNX-45).
 *
 * `revision` is echoed from the document and never stored twice: the set is anchored to
 * a document, and that document owns its revision. `proposalStatus` describes whether an
 * extraction has run for the page (`none` until KNX-48).
 *
 * The geometry is passed through exactly as the client sent it: the server is a
 * caretaker, not an interpreter, of what was drawn on the original plan.
 */
class PlanRoomSetResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        /** @var KnxDocument $document */
        $document = $this->resource['document'];

        return [
            'documentId' => (string) $document->getKey(),
            'revision' => $document->revision,
            'page' => $this->resource['page'],
            'pageWidthPt' => $this->resource['pageWidthPt'] !== null
                ? (float) $this->resource['pageWidthPt']
                : null,
            'pageHeightPt' => $this->resource['pageHeightPt'] !== null
                ? (float) $this->resource['pageHeightPt']
                : null,
            'proposalStatus' => $this->resource['proposalStatus'],
            'rooms' => $this->resource['rooms']
                ->map(fn (KnxPlanRoom $room): array => [
                    'roomId' => (string) $room->room_id,
                    'polygon' => $room->polygon,
                    'labelAt' => $room->label_at,
                    'areaM2' => $room->area_m2 !== null ? (float) $room->area_m2 : null,
                    'source' => $room->source,
                    'state' => $room->state,
                    'geometry' => $room->effectiveGeometry(),
                    'holes' => $room->holes ?? [],
                    'geometryNote' => $room->geometry_note,
                    'processingVersion' => $room->processing_version,
                ])
                ->values()
                ->all(),
        ];
    }
}
