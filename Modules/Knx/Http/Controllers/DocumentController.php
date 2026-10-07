<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Knx\Http\Resources\DocumentResource;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\DocumentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plannen & documenten (§4.6 and the upload of §4.11).
 *
 * Until KNX-3 the office could only read documents: nothing in this module ever
 * received a file, so the plan viewer was finished and showed an empty state. The
 * upload is what closes that hole, and it is deliberately the *only* writer here —
 * no Filament resource, no field upload.
 */
class DocumentController extends Controller
{
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'nullable', 'string'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $projectCode = $validated['project'] ?? null;
        $term = $validated['q'] ?? null;

        $documents = KnxDocument::query()
            ->with(['project', 'uploadedBy', 'approvedBy'])
            ->when($projectCode !== null && trim($projectCode) !== '', fn (Builder $query) => $query->whereHas(
                'project',
                fn (Builder $inner) => $inner->where('code', $projectCode),
            ))
            ->when($term !== null && trim($term) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get();

        // No signed URLs in a list: see DocumentResource.
        return DocumentResource::list($documents, $request);
    }

    public function show(string $id): DocumentResource
    {
        return DocumentResource::make(
            KnxDocument::query()->with(['project', 'uploadedBy', 'approvedBy'])->findOrFail($id),
        )->withUrl();
    }

    /**
     * `POST /documents` (multipart) — a plan or document enters the system.
     *
     * The response is the read shape the office already parses, signed URL included,
     * so the viewer needs no second parser. A repeated `clientId` answers 200 with the
     * same document (never a duplicate revision); an unknown project or `supersedes`
     * answers 404; a file over 50 MB answers 413.
     */
    public function store(Request $request, DocumentService $documents): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'project' => ['required', 'string', 'max:32'],
            'kind' => ['required', 'string', 'max:40'],
            'revision' => ['sometimes', 'nullable', 'string', 'max:20'],
            // Column ids are integers; the contract calls it "id de documento".
            'supersedes' => ['sometimes', 'nullable', 'integer'],
            'clientId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'uploadedAt' => ['sometimes', 'nullable', 'date'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];

        // 413, not a field error: what is too large is the request body itself, and
        // the contract names that code. The message is the worklist entry the office
        // needs, and the envelope stays the same shape as every other failure.
        if ($file->getSize() > DocumentService::MAX_BYTES) {
            abort(413, __('knx::documents.too_large'));
        }

        // Unknown code is a 404, and it is also what scopes the tenant: the middleware
        // already fixed Electro Bertels, so the project lookup cannot cross it.
        $project = KnxProject::query()->where('code', $validated['project'])->firstOrFail();

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        $result = $documents->create($project, $employee, $validated);

        return response()->json(
            DocumentResource::make($result['document'])->withUrl()->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * Behind `signed` and NOT behind `auth:sanctum`: a browser opening an `<a href>`
     * cannot send a bearer token, which is exactly why the contract asks for a
     * temporary signed URL. The signature is the credential, and it expires.
     */
    public function download(string $id): StreamedResponse
    {
        $document = KnxDocument::query()->findOrFail($id);
        $path = $document->path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404, __('knx::documents.file_missing'));

        return Storage::disk('local')->download($path, $document->name);
    }
}
