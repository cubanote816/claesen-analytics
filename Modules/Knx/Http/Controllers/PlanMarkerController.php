<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\PlanMarkerSetResource;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\PlanMarkerService;

/**
 * The boards placed on a plan revision (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * Both routes are addressed by project code and document id, and the document is
 * always resolved **inside the project**: a document id from another project is a
 * 404, not a way to read or write someone else's markers. The set belongs to the
 * document, never to the project.
 */
class PlanMarkerController extends Controller
{
    public function __construct(private readonly PlanMarkerService $markers) {}

    /**
     * The set of one page (default 1). An empty set is a valid answer: the plan has
     * no markers yet, which is the normal state right after an upload.
     */
    public function show(Request $request, string $code, string $documentId): array
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $document = $this->document($code, $documentId);

        return PlanMarkerSetResource::make(
            $this->markers->forDocument($document, (int) ($validated['page'] ?? 1)),
        )->resolve($request);
    }

    /**
     * Replace the whole set of one page. Idempotent: the same body twice leaves the
     * same rows. `carryOverFrom` uses the previous revision's markers as the base and
     * the body's own markers win per board.
     */
    public function update(Request $request, string $code, string $documentId): array
    {
        $validated = $request->validate([
            // Present in the contract body; when it comes it must be this document.
            'documentId' => ['sometimes', 'nullable', 'string', 'max:64'],
            // Accepted for compatibility with the sent body, but ignored: the revision
            // label belongs to the document and is echoed from it, never stored twice.
            'revision' => ['sometimes', 'nullable', 'string', 'max:20'],
            'page' => ['required', 'integer', 'min:1'],
            'pageWidthPt' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'pageHeightPt' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            // `present`, not `required`: the set replaces the whole page, so an EMPTY
            // set is a valid body — it clears the page (there is no DELETE) and it is
            // also the base for a full `carryOverFrom`. Laravel's `required` rejects an
            // empty array, which made removing the last marker a 422.
            'markers' => ['present', 'array', 'max:500'],
            // `distinct` because the table has one marker per board per document: two
            // DOM markers sharing a key would mean one delete removes both, and the
            // database would reject the second anyway.
            'markers.*.boardId' => ['required', 'string', 'max:128', 'distinct'],
            'markers.*.nx' => ['required', 'numeric', 'between:0,1'],
            'markers.*.ny' => ['required', 'numeric', 'between:0,1'],
            'carryOverFrom' => ['sometimes', 'nullable', 'integer'],
        ]);

        $document = $this->document($code, $documentId);

        if (isset($validated['documentId']) && (string) $validated['documentId'] !== (string) $document->getKey()) {
            throw ValidationException::withMessages(['documentId' => __('knx::documents.document_mismatch')]);
        }

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        return PlanMarkerSetResource::make(
            $this->markers->replace($document->project, $document, $employee, $validated),
        )->resolve($request);
    }

    /**
     * Resolve the document inside the project's own scope. Unknown code or unknown
     * document in that project is a 404; a document of another project is unknown here.
     */
    private function document(string $code, string $documentId): KnxDocument
    {
        $project = KnxProject::query()->where('code', $code)->firstOrFail();

        return KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->with('project')
            ->findOrFail((int) $documentId);
    }
}
