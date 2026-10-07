<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanMarker;

/**
 * The marker set of one document page (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * `revision` is the **document's** own label, echoed here and not stored on the
 * marker rows: the set is already anchored to a document, and that document owns its
 * revision, so a second copy would be a second source of truth. The server does not
 * validate it either — the UI is what compares a rendered plan's revision against
 * the one these markers were saved with.
 *
 * The resource's "model" is the payload array the service builds (`document`, `page`,
 * geometry, `markers`), not a single row: the set is many rows plus its document.
 */
class PlanMarkerSetResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        /** @var KnxDocument $document */
        $document = $this->resource['document'];

        return [
            'documentId' => (string) $document->getKey(),
            'revision' => $document->revision,
            'page' => $this->resource['page'],
            // The client's opaque measurement, as it was sent. Null means the client
            // did not know it, which is an honest answer, not a missing field.
            'pageWidthPt' => $this->resource['pageWidthPt'] !== null
                ? (float) $this->resource['pageWidthPt']
                : null,
            'pageHeightPt' => $this->resource['pageHeightPt'] !== null
                ? (float) $this->resource['pageHeightPt']
                : null,
            'markers' => $this->resource['markers']
                ->map(fn (KnxPlanMarker $marker): array => [
                    'boardId' => $marker->board_id,
                    'nx' => (float) $marker->nx,
                    'ny' => (float) $marker->ny,
                ])
                ->values()
                ->all(),
        ];
    }
}
