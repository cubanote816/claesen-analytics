<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanCleanProfile;

/**
 * The clean-plan profile of a plan page (KNX-48).
 *
 * One profile per document and page. `PUT` replaces the page's whole `roles` map and
 * stamps the document's CURRENT revision on it, so a client can later tell a profile
 * made for another revision (`revision` vs the document's `documentRevision`). The
 * server stores the map as sent and never reads the PDF.
 */
class PlanCleanProfileService
{
    /**
     * @return array{document: KnxDocument, page: int, profile: KnxPlanCleanProfile|null}
     */
    public function forDocument(KnxDocument $document, int $page): array
    {
        return [
            'document' => $document,
            'page' => $page,
            'profile' => KnxPlanCleanProfile::query()
                ->where('document_id', $document->getKey())
                ->where('page', $page)
                ->first(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input  validated input
     * @return array{document: KnxDocument, page: int, profile: KnxPlanCleanProfile|null}
     */
    public function replace(KnxDocument $document, KnxEmployee $employee, array $input): array
    {
        $page = (int) $input['page'];

        KnxPlanCleanProfile::query()->updateOrCreate(
            ['document_id' => $document->getKey(), 'page' => $page],
            [
                'project_id' => $document->project_id,
                // An empty map is stored as `{}`: it is a valid "no overrides" profile.
                'roles' => $input['roles'],
                'boundaries' => array_map(
                    fn (array $segment): array => array_map(fn (array $p): array => [(float) $p[0], (float) $p[1]], $segment),
                    $input['boundaries'] ?? [],
                ),
                'revision' => $document->revision,
                'processing_version' => $input['processingVersion'] ?? null,
                'updated_by_employee_id' => $employee->getKey(),
            ],
        );

        return $this->forDocument($document, $page);
    }
}
