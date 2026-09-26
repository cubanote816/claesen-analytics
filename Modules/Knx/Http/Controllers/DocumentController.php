<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Knx\Http\Resources\DocumentResource;
use Modules\Knx\Models\KnxDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plannen & documenten (§4.6).
 *
 * Uploading stays out of scope on purpose (the contract says so): plans are added
 * from the Filament backoffice or from the field app.
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
        return new DocumentResource(
            KnxDocument::query()->with(['project', 'uploadedBy', 'approvedBy'])->findOrFail($id),
            withUrl: true,
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
