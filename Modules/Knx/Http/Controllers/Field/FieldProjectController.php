<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\FieldDeviceResource;
use Modules\Knx\Http\Resources\FieldPlanResource;
use Modules\Knx\Http\Resources\FieldProjectResource;
use Modules\Knx\Http\Resources\ProjectBoardsResource;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanMarker;
use Modules\Knx\Models\KnxPlanRoom;
use Modules\Knx\Services\FieldProjectService;
use Modules\Knx\Services\RoomSheetService;

/**
 * `GET /field/projects/{code}` and `…/plans` — what the app caches to register and
 * to work with no connection (V11.b, CLA-609).
 *
 * Both go through `FieldProjectService`, so the project's scope rule is answered
 * once for the whole `/field/*` surface.
 */
class FieldProjectController extends Controller
{
    public function __construct(private readonly FieldProjectService $projects) {}

    public function show(Request $request, string $code): FieldProjectResource
    {
        return FieldProjectResource::make(
            $this->projects->resolveAuthorized($this->technician($request), $code),
        );
    }

    public function plans(Request $request, string $code): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        return FieldPlanResource::list($this->projects->plansFor($project), $request);
    }

    /**
     * `GET /field/projects/{code}/devices` (V11.f, CLA-635).
     *
     * Answers the same `FieldDevice` shape the registration does, so the app has one
     * device shape. What it adds over the registration is the whole list: the card's
     * progress and the plan's per-room counts are both derived from it.
     */
    public function devices(Request $request, string $code): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        return FieldDeviceResource::list($this->projects->devicesFor($project), $request);
    }

    private function technician(Request $request): KnxEmployee
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        return $technician;
    }

    /**
     * `GET /field/projects/{code}/boards` (KNX-25) — the same cabinet structure the
     * office reads, so the field app registers a device against the real boards and
     * rooms of the project instead of the mock's fixed list.
     */
    public function boards(Request $request, string $code): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        $boards = KnxBoard::query()
            ->where('project_id', $project->getKey())
            ->with(['modules.links', 'modules.updatedBy', 'updatedBy'])
            ->orderBy('id')
            ->get();

        return ProjectBoardsResource::make([
            'project' => $project,
            'boards' => $boards,
        ])->resolve($request);
    }

    /**
     * `GET /field/projects/{code}/rooms` (KNX-47) — the room sheets of the whole
     * project, so the app caches them and shows a room's ficha with no connection.
     */
    public function rooms(Request $request, string $code, RoomSheetService $sheets): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        return $sheets->forProject($project, $request);
    }

    /**
     * `GET /field/projects/{code}/plans/{documentId}/model` (KNX-47) — the minimal model
     * to cache offline: only the CONFIRMED rooms of one page plus that page's board
     * markers.
     *
     * `proposed` rooms are deliberately absent: the field must not read as truth a
     * polygon an extraction or a carry-over suggested and the office has not accepted.
     */
    public function planModel(Request $request, string $code, string $documentId): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        $page = (int) $request->integer('page', 1);

        // The document is resolved inside the project: a document of another project is
        // a 404, not a way to read someone else's plan.
        $document = KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->findOrFail($documentId);

        $rooms = KnxPlanRoom::query()
            ->where('document_id', $document->getKey())
            ->where('page', $page)
            ->where('state', KnxPlanRoom::STATE_CONFIRMED)
            ->orderBy('id')
            ->get();

        $markers = KnxPlanMarker::query()
            ->where('document_id', $document->getKey())
            ->where('page', $page)
            ->orderBy('id')
            ->get();

        // All rows of a page share the client's measurement of it; either set can
        // provide it.
        $first = $rooms->first() ?? $markers->first();

        return [
            'documentId' => (string) $document->getKey(),
            'revision' => $document->revision,
            'page' => $page,
            'pageWidthPt' => $first?->page_width_pt !== null ? (float) $first->page_width_pt : null,
            'pageHeightPt' => $first?->page_height_pt !== null ? (float) $first->page_height_pt : null,
            'rooms' => $rooms
                ->map(fn (KnxPlanRoom $room): array => [
                    'roomId' => (string) $room->room_id,
                    'polygon' => $room->polygon,
                    'labelAt' => $room->label_at,
                    'areaM2' => $room->area_m2 !== null ? (float) $room->area_m2 : null,
                    'source' => $room->source,
                    'state' => $room->state,
                ])
                ->values()
                ->all(),
            'markers' => $markers
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
