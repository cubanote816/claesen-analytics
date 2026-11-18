<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanRoom;
use Modules\Knx\Models\KnxProject;

/**
 * The room model of a plan revision (KNX-45).
 *
 * Same anchoring as the board markers: the set belongs to a DOCUMENT —a revision— and a
 * page, never to the project, because the original drawing is shown and the data is
 * overlaid on it. `PUT` replaces the whole set of one page and is idempotent, and a new
 * revision never touches the previous one's model.
 *
 * `carryOverFrom` copies the previous revision's model of the SAME page as a starting
 * point, and what it copies goes back to `proposed`: it is a suggestion to re-confirm,
 * not a fact inherited from a drawing that changed.
 *
 * The geometry is opaque. The server stores what the client measured and never parses
 * the PDF; `proposalStatus` stays `none` until KNX-48 adds an extraction.
 */
class PlanRoomService
{
    /** No extraction has run for a page yet (KNX-48 owns the other states). */
    public const PROPOSAL_NONE = 'none';

    /**
     * @return array{document: KnxDocument, page: int, pageWidthPt: float|null, pageHeightPt: float|null, proposalStatus: string, rooms: Collection<int, KnxPlanRoom>}
     */
    public function forDocument(KnxDocument $document, int $page): array
    {
        $rooms = KnxPlanRoom::query()
            ->where('document_id', $document->getKey())
            ->where('page', $page)
            ->orderBy('id')
            ->get();

        // All rooms of a page share the client's measurement of that page; the first is
        // the set's.
        $first = $rooms->first();

        return [
            'document' => $document,
            'page' => $page,
            'pageWidthPt' => $first?->page_width_pt,
            'pageHeightPt' => $first?->page_height_pt,
            'proposalStatus' => self::PROPOSAL_NONE,
            'rooms' => $rooms,
        ];
    }

    /**
     * Replace the whole room model of one document page.
     *
     * @param  array<string, mixed>  $input  validated input
     * @return array{document: KnxDocument, page: int, pageWidthPt: float|null, pageHeightPt: float|null, proposalStatus: string, rooms: Collection<int, KnxPlanRoom>}
     */
    public function replace(KnxProject $project, KnxDocument $document, KnxEmployee $employee, array $input): array
    {
        $page = (int) $input['page'];
        $effective = $this->effectiveRooms($project, $input);

        DB::transaction(function () use ($project, $document, $employee, $page, $effective, $input): void {
            // One page's set is replaced whole...
            KnxPlanRoom::query()
                ->where('document_id', $document->getKey())
                ->where('page', $page)
                ->delete();

            // ...and a room that used to sit on another page of the same document is
            // moved instead of colliding with the unique(document, room) key: a room is
            // in one place on one drawing.
            if ($effective !== []) {
                KnxPlanRoom::query()
                    ->where('document_id', $document->getKey())
                    ->whereIn('room_id', array_keys($effective))
                    ->delete();
            }

            foreach ($effective as $roomId => $room) {
                KnxPlanRoom::create([
                    'project_id' => $project->getKey(),
                    'document_id' => $document->getKey(),
                    'room_id' => $roomId,
                    'page' => $page,
                    'polygon' => $room['polygon'],
                    'label_at' => $room['labelAt'],
                    'area_m2' => $room['areaM2'],
                    'source' => $room['source'],
                    'state' => $room['state'],
                    'placed_by_employee_id' => $employee->getKey(),
                    // The client's opacity, stored as sent.
                    'page_width_pt' => $input['pageWidthPt'] ?? null,
                    'page_height_pt' => $input['pageHeightPt'] ?? null,
                ]);
            }
        });

        return $this->forDocument($document, $page);
    }

    /**
     * The final set, keyed by room: the carried rooms as a base, the request's own rooms
     * on top. `roomId` is `distinct` in the payload, so last-write-wins only applies
     * between carry-over and payload.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, array{polygon: array<int, array<int, float>>, labelAt: array<int, float>|null, areaM2: mixed, source: string, state: string}>
     */
    private function effectiveRooms(KnxProject $project, array $input): array
    {
        $byRoom = [];

        $sourceId = $input['carryOverFrom'] ?? null;

        if ($sourceId !== null && $sourceId !== '') {
            // Scoped to the project AND the same page: carrying from another project's
            // document would put a foreign room on this plan, and flattening every page
            // into one would silently move rooms that live on another sheet.
            $source = KnxDocument::query()
                ->where('project_id', $project->getKey())
                ->findOrFail((int) $sourceId);

            $carried = KnxPlanRoom::query()
                ->where('document_id', $source->getKey())
                ->where('page', (int) $input['page'])
                ->orderBy('id')
                ->get();

            foreach ($carried as $room) {
                $byRoom[$room->room_id] = [
                    'polygon' => $room->polygon,
                    'labelAt' => $room->label_at,
                    'areaM2' => $room->area_m2,
                    'source' => $room->source,
                    // A carry-over is a starting point, never a confirmation inherited
                    // from a drawing the new revision may have changed.
                    'state' => KnxPlanRoom::STATE_PROPOSED,
                ];
            }
        }

        foreach ($input['rooms'] as $room) {
            $byRoom[(int) $room['roomId']] = [
                'polygon' => $room['polygon'],
                'labelAt' => $room['labelAt'] ?? null,
                'areaM2' => $room['areaM2'] ?? null,
                'source' => $room['source'],
                'state' => $room['state'],
            ];
        }

        return $byRoom;
    }
}
