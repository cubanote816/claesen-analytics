<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanMarker;
use Modules\Knx\Models\KnxProject;

/**
 * Board markers on a plan revision (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * A set belongs to a DOCUMENT —a revision, per page—, never to the project. `PUT`
 * replaces the whole set and is idempotent: five markers placed by one person are
 * not an edit stream, and replacing avoids having to reconcile deltas. Marking a new
 * revision never touches the previous document's markers: they stay attached to their
 * own revision and remain correct for it.
 *
 * `carryOverFrom` is the cheap convenience the contract asks for: the front starts the
 * new revision from the previous one's markers and only sends what changed. The
 * carried markers are the base and the request's markers win per `boardId`, so the
 * same `PUT` repeated is still the same set.
 */
class PlanMarkerService
{
    /**
     * The set of a document for one page, in the contract's shape.
     *
     * @return array{document: KnxDocument, page: int, pageWidthPt: float|null, pageHeightPt: float|null, markers: Collection<int, KnxPlanMarker>}
     */
    public function forDocument(KnxDocument $document, int $page): array
    {
        $markers = KnxPlanMarker::query()
            ->where('document_id', $document->getKey())
            ->where('page', $page)
            ->orderBy('id')
            ->get();

        // Every marker of a set carries the same geometry, because the client measured
        // one page; the first one is the set's.
        $first = $markers->first();

        return [
            'document' => $document,
            'page' => $page,
            'pageWidthPt' => $first?->page_width_pt,
            'pageHeightPt' => $first?->page_height_pt,
            'markers' => $markers,
        ];
    }

    /**
     * Replace the whole marker set of one document page.
     *
     * @param  array<string, mixed>  $input  validated input
     * @return array{document: KnxDocument, page: int, pageWidthPt: float|null, pageHeightPt: float|null, markers: Collection<int, KnxPlanMarker>}
     */
    public function replace(KnxProject $project, KnxDocument $document, KnxEmployee $employee, array $input): array
    {
        $page = (int) $input['page'];
        $effective = $this->effectiveMarkers($project, $input);

        DB::transaction(function () use ($project, $document, $employee, $page, $effective, $input): void {
            // One page's set is replaced whole...
            KnxPlanMarker::query()
                ->where('document_id', $document->getKey())
                ->where('page', $page)
                ->delete();

            // ...and a board that used to sit on another page of the same document is
            // moved instead of colliding with the unique(document, board) key: a board
            // is in one place, so the newest placement is the true one.
            if ($effective !== []) {
                KnxPlanMarker::query()
                    ->where('document_id', $document->getKey())
                    ->whereIn('board_id', array_keys($effective))
                    ->delete();
            }

            foreach ($effective as $boardId => $marker) {
                KnxPlanMarker::create([
                    'project_id' => $project->getKey(),
                    'document_id' => $document->getKey(),
                    'board_id' => $boardId,
                    'page' => $page,
                    // The client's own measurement, stored as it was sent. The server
                    // does not parse the PDF and has no opinion about the geometry.
                    'page_width_pt' => $input['pageWidthPt'] ?? null,
                    'page_height_pt' => $input['pageHeightPt'] ?? null,
                    'nx' => $marker['nx'],
                    'ny' => $marker['ny'],
                    'placed_by_employee_id' => $employee->getKey(),
                ]);
            }
        });

        return $this->forDocument($document, $page);
    }

    /**
     * The final set, keyed by board: the carried markers as a base, the request's own
     * markers on top. A `boardId` sent twice is rejected by validation before this,
     * so the last-write-wins rule only ever applies between carry-over and payload.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array{nx: float, ny: float}>
     */
    private function effectiveMarkers(KnxProject $project, array $input): array
    {
        $byBoard = [];

        $sourceId = $input['carryOverFrom'] ?? null;

        if ($sourceId !== null && $sourceId !== '') {
            // Scoped to the project: carrying markers from another project's document
            // would put a foreign board on this plan, so an unknown id is a 404.
            $source = KnxDocument::query()
                ->where('project_id', $project->getKey())
                ->findOrFail((int) $sourceId);

            // Scoped to the SAME page: `carryOverFrom` copies the previous revision's set
            // of this page, not the whole document flattened into one. Reading every page
            // silently MOVED a board that lived on another page (the unique key is document
            // + board), which is a surprise no caller asked for. A plan with markers on
            // several pages carries each page on its own PUT.
            $carried = KnxPlanMarker::query()
                ->where('document_id', $source->getKey())
                ->where('page', (int) $input['page'])
                ->orderBy('id')
                ->get();

            foreach ($carried as $marker) {
                $byBoard[$marker->board_id] = ['nx' => (float) $marker->nx, 'ny' => (float) $marker->ny];
            }
        }

        foreach ($input['markers'] as $marker) {
            $byBoard[$marker['boardId']] = ['nx' => (float) $marker['nx'], 'ny' => (float) $marker['ny']];
        }

        return $byBoard;
    }
}
