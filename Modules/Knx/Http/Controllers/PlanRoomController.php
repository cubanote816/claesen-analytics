<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\PlanRoomSetResource;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanRoom;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\PlanRoomService;
use Modules\Knx\Support\KnxTenant;
use Modules\Knx\Support\PlanGeometry;

/**
 * The room model of a plan revision (KNX-45).
 *
 * Both routes are addressed by project code and document id, and the document is always
 * resolved INSIDE the project: a document id from another project or organization is a
 * 404, not a way to read or write someone else's model.
 *
 * The set belongs to the document and the page, never to the project, for the same
 * reason the markers do: the plan is shown as it was drawn and the data is overlaid.
 */
class PlanRoomController extends Controller
{
    public function __construct(private readonly PlanRoomService $rooms) {}

    public function show(Request $request, string $code, string $documentId): array
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $document = $this->document($code, $documentId);

        return PlanRoomSetResource::make(
            $this->rooms->forDocument($document, (int) ($validated['page'] ?? 1)),
        )->resolve($request);
    }

    public function update(Request $request, string $code, string $documentId): array
    {
        $document = $this->document($code, $documentId);
        $project = $document->project;

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'pageWidthPt' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'pageHeightPt' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            // Same-page carry-over only; scoped to the project inside the service.
            'carryOverFrom' => ['sometimes', 'nullable', 'integer'],
            // `present`, not `required`: clearing a page's model is a valid operation,
            // the same lesson KNX-18 left on the markers.
            'rooms' => ['present', 'array', 'max:500'],
            // A room appears once in the set (the table enforces one row per document).
            'rooms.*.roomId' => [
                'required',
                'distinct',
                Rule::exists('knx_project_rooms', 'id')->where('project_id', $project->getKey()),
            ],
            'rooms.*.polygon' => ['required', 'array', 'min:3', 'max:'.PlanGeometry::MAX_VERTICES],
            'rooms.*.polygon.*' => ['array', 'size:2'],
            'rooms.*.polygon.*.0' => ['required', 'numeric', 'between:0,1'],
            'rooms.*.polygon.*.1' => ['required', 'numeric', 'between:0,1'],
            'rooms.*.labelAt' => ['sometimes', 'nullable', 'array', 'size:2'],
            'rooms.*.labelAt.0' => ['required_with:rooms.*.labelAt', 'numeric', 'between:0,1'],
            'rooms.*.labelAt.1' => ['required_with:rooms.*.labelAt', 'numeric', 'between:0,1'],
            'rooms.*.areaM2' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rooms.*.source' => ['required', Rule::in(KnxPlanRoom::SOURCES)],
            'rooms.*.state' => ['required', Rule::in(KnxPlanRoom::STATES)],
            // KNX-48 — provenance and topology. All optional: a PUT without them stores
            // the legacy default (see PlanRoomService).
            'rooms.*.geometry' => ['sometimes', 'nullable', Rule::in(KnxPlanRoom::GEOMETRIES)],
            'rooms.*.holes' => ['sometimes', 'nullable', 'array', 'max:50'],
            'rooms.*.holes.*' => ['array', 'min:3', 'max:'.PlanGeometry::MAX_VERTICES],
            'rooms.*.holes.*.*' => ['array', 'size:2'],
            'rooms.*.holes.*.*.0' => ['required', 'numeric', 'between:0,1'],
            'rooms.*.holes.*.*.1' => ['required', 'numeric', 'between:0,1'],
            'rooms.*.geometryNote' => [
                'sometimes',
                'nullable',
                Rule::in(array_merge(...array_values(KnxPlanRoom::NOTES_BY_GEOMETRY))),
            ],
            'rooms.*.processingVersion' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $this->assertConsistentRooms($validated['rooms']);

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        return PlanRoomSetResource::make(
            $this->rooms->replace($project, $document, $employee, $validated),
        )->resolve($request);
    }

    /**
     * What the field rules cannot say: a note must belong to its geometry, and the
     * geometry must be a valid topology (S5, S7). Errors use the dotted `rooms.N.field`
     * key the rest of the payload uses.
     *
     * @param  array<int, array<string, mixed>>  $rooms
     */
    private function assertConsistentRooms(array $rooms): void
    {
        $errors = [];

        foreach ($rooms as $index => $room) {
            $geometry = $room['geometry'] ?? KnxPlanRoom::defaultGeometry($room['source']);
            $note = $room['geometryNote'] ?? null;

            if ($note !== null && ! in_array($note, KnxPlanRoom::NOTES_BY_GEOMETRY[$geometry], true)) {
                $errors["rooms.{$index}.geometryNote"] = ["The note \"{$note}\" is not allowed with geometry \"{$geometry}\"."];
            }

            $problem = PlanGeometry::validate($room['polygon'], $room['holes'] ?? []);

            if ($problem !== null) {
                $errors["rooms.{$index}.{$problem['field']}"] = [$problem['message']];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The document, resolved inside the project and the organization. An unknown code,
     * a foreign project and a document of another project are all 404.
     */
    private function document(string $code, string $documentId): KnxDocument
    {
        $project = KnxProject::query()
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();

        return KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->findOrFail($documentId);
    }
}
