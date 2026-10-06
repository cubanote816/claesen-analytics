<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Support\IdempotentWrite;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Uploading a plan or document from the office (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * The upload is the first thing in this domain that *receives* bytes, so the server
 * derives what it can see instead of trusting the client: `size_bytes`, `mime_type`
 * and `pages` all come from the file. The one thing it deliberately does NOT do is
 * interpret the document — no rasterising, no geometry (the plan viewer measures the
 * page itself and sends the markers' fractions).
 *
 * Two rules are the whole point of the class:
 *
 *   1. **The client's `clientId` decides identity.** A 3 MB upload cut by the network
 *      and retried by the user must not create a second revision; the same key
 *      returns the row that is already there (200), never a second one. This is the
 *      same pattern the field writes use, and it shares `IdempotentWrite`.
 *   2. **`supersedes` is explicit and happens in one transaction.** The architect
 *      renames the file between revisions, so "same filename means same series" is
 *      wrong; the relation is a stored fact. Marking the old revision not current and
 *      the new one current is one atomic step, so a failure in the middle can never
 *      leave both current or neither.
 */
class DocumentService
{
    /** 50 MB (docs/BACKEND-API.md §4.11). The photo archive is 38 MB and must fit. */
    public const MAX_BYTES = 50 * 1024 * 1024;

    /**
     * Create the document, or return the one this `clientId` already produced.
     *
     * @param  array<string, mixed>  $input  validated input (`file` is an UploadedFile)
     * @return array{document: KnxDocument, created: bool}
     */
    public function create(KnxProject $project, KnxEmployee $uploadedBy, array $input): array
    {
        $clientId = $input['clientId'] ?? null;

        $replay = $this->replayFor($project, $clientId);

        if ($replay !== null) {
            return ['document' => $replay, 'created' => false];
        }

        $supersedes = $this->supersedesFor($project, $input['supersedes'] ?? null);

        $document = IdempotentWrite::run(
            fn (): KnxDocument => $this->write($project, $uploadedBy, $input, $supersedes),
            fn (): ?KnxDocument => $this->replayFor($project, $clientId),
        );

        return ['document' => $document, 'created' => $document->wasRecentlyCreated];
    }

    /**
     * The write itself, in one transaction.
     *
     * The row is created before the file is stored because the stored path is
     * derived from the id (the column the download already uses). Storing the file
     * inside the transaction means a failed put rolls the row back with it, so a
     * document can never reference bytes that are not there.
     *
     * @param  array<string, mixed>  $input
     */
    private function write(KnxProject $project, KnxEmployee $uploadedBy, array $input, ?KnxDocument $supersedes): KnxDocument
    {
        /** @var UploadedFile $file */
        $file = $input['file'];

        return DB::transaction(function () use ($project, $uploadedBy, $input, $file, $supersedes): KnxDocument {
            $document = KnxDocument::create([
                'client_id' => $input['clientId'] ?? null,
                'project_id' => $project->getKey(),
                'name' => $file->getClientOriginalName(),
                'kind' => $input['kind'],
                'mime_type' => $this->mimeType($file),
                'size_bytes' => $file->getSize(),
                'pages' => $this->pageCount($file),
                'path' => null,
                'revision' => $input['revision'] ?? null,
                // The new revision is the current one by definition; when nothing is
                // superseded this is still the only document of its series.
                'is_current' => true,
                'uploaded_by_employee_id' => $uploadedBy->getKey(),
                'uploaded_at' => isset($input['uploadedAt'])
                    ? Carbon::parse($input['uploadedAt'])
                    : now(),
            ]);

            $document->forceFill(['path' => $this->storeFile($project, $document, $file)])->save();

            if ($supersedes !== null) {
                // Only one UPDATE: the previous revision leaves the series. Inside the
                // same transaction as the insert, so both facts land together.
                KnxDocument::query()
                    ->whereKey($supersedes->getKey())
                    ->update(['is_current' => false]);
            }

            return $document->load(['project', 'uploadedBy', 'approvedBy']);
        });
    }

    /**
     * The document this `clientId` already produced, if any.
     *
     * Same key in another project is a client bug, not a replay: answering with the
     * stored row would hand one project the document of another. Both the first read
     * and the recovery read after a lost race go through here, so the rule cannot
     * drift between them.
     */
    private function replayFor(KnxProject $project, ?string $clientId): ?KnxDocument
    {
        if ($clientId === null || $clientId === '') {
            return null;
        }

        $document = KnxDocument::query()
            ->with(['project', 'uploadedBy', 'approvedBy'])
            ->where('client_id', $clientId)
            ->first();

        if ($document === null) {
            return null;
        }

        if ((int) $document->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages(['clientId' => [__('knx::documents.client_id_reused')]]);
        }

        return $document;
    }

    /**
     * The revision this upload replaces. It has to be a document of this project:
     * `supersedes` is scoped that way instead of by filename, because the architect
     * renames the file between revisions and matching names would link the wrong
     * plans. An unknown id is a 404, as the contract says, not a validation error.
     */
    private function supersedesFor(KnxProject $project, mixed $id): ?KnxDocument
    {
        if ($id === null || $id === '') {
            return null;
        }

        return KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->findOrFail((int) $id);
    }

    /**
     * Where the bytes live: a path derived from the project and the document id, which
     * is the only identity that is stable for the life of the row.
     */
    private function storeFile(KnxProject $project, KnxDocument $document, UploadedFile $file): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()) ?: 'document';

        return (string) Storage::disk('local')->putFileAs(
            'knx/plans/'.$project->code,
            $file,
            $document->getKey().'-'.$safe,
        );
    }

    /**
     * The content type of the bytes, guessed from the file (finfo), never from the
     * client's declared type: the header can say anything.
     */
    private function mimeType(UploadedFile $file): string
    {
        return mb_substr((string) $file->getMimeType(), 0, 100);
    }

    /**
     * Pages of the uploaded document, counted from the file. Anything that is not a
     * PDF is one sheet (the column default), and a PDF that cannot be parsed is one
     * page rather than a failed upload: the office still needs the document.
     */
    private function pageCount(UploadedFile $file): int
    {
        if ($this->mimeType($file) !== 'application/pdf') {
            return 1;
        }

        try {
            return max(1, count((new Parser)->parseContent((string) $file->get())->getPages()));
        } catch (Throwable) {
            return 1;
        }
    }
}
