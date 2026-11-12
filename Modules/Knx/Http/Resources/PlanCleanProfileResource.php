<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanCleanProfile;

/**
 * The clean-plan profile of one plan page (KNX-48).
 *
 * `revision` is the document revision stamped at the last save; `documentRevision` is
 * the document's current one. `roles` is always a JSON object — `{}` when there is no
 * profile — because group ids can look like list indexes and an empty PHP array would
 * encode as `[]`.
 */
class PlanCleanProfileResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        /** @var KnxDocument $document */
        $document = $this->resource['document'];
        /** @var KnxPlanCleanProfile|null $profile */
        $profile = $this->resource['profile'];

        return [
            'documentId' => (string) $document->getKey(),
            'page' => $this->resource['page'],
            'revision' => $profile?->revision,
            'documentRevision' => $document->revision,
            'processingVersion' => $profile?->processing_version,
            'roles' => (object) ($profile?->roles ?? []),
            'boundaries' => $profile?->boundaries ?? [],
            'updatedAt' => $profile?->updated_at?->toIso8601String(),
        ];
    }
}
