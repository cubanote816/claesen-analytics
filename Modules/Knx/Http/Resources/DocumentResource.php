<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Knx\Models\KnxDocument;

/**
 * `DocumentFile` of docs/BACKEND-API.md §3.
 *
 * `size` is human text ("4,2 MB") rendered from the stored `size_bytes`: the
 * contract accepts either, and §5 notes the choice must be documented — keeping
 * the number is lossless, and nothing in the domain wants the text back.
 *
 * `url` is a **temporary signed URL**, and it is only built for a single document
 * (`GET /documents/{id}`), which is what the contract asks for. A list returns
 * `url: null`, exactly like the office app's own fixture: building N signed URLs
 * to display a table would be wasteful, and the browser cannot send a bearer
 * token on a plain `<a href>` download — the signature is the credential, which
 * is why it cannot be behind the auth middleware.
 *
 * A document whose file is not actually on disk gets `url: null` instead of a
 * link that 404s.
 *
 * @property-read KnxDocument $resource
 */
class DocumentResource extends KnxResource
{
    private bool $withUrl = false;

    /**
     * Ask for a signed download URL.
     *
     * A fluent method and NOT a constructor argument on purpose: Laravel passes the
     * collection key as the second constructor argument when it builds a list, so a
     * `bool $withUrl` parameter was silently set by the collection's own keys (see
     * `KnxResource::list()`). The parameter is gone so the trap cannot come back.
     */
    public function withUrl(): static
    {
        $this->withUrl = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'id' => (string) $document->getKey(),
            'name' => $document->name,
            'projectCode' => $document->project?->code,
            'projectName' => $document->project?->name,
            'kind' => $document->kind,
            // What the file *is*, derived from the stored column (or the extension for
            // documents written before uploads existed) instead of the name, so the
            // office can filter plans by content type. Null is a real answer: the
            // office never stored a content type for the demo fixture's archive.
            'mimeType' => $document->resolvedMimeType(),
            'size' => self::humanSize($document->size_bytes),
            'pages' => $document->pages,
            'uploadedAt' => $document->uploaded_at->format('Y-m-d'),
            'uploadedBy' => $document->uploadedBy?->shortName(),
            'url' => $this->withUrl ? $this->signedUrl() : null,
            'revision' => $document->revision,
            'isCurrent' => $document->is_current,
            'approvedBy' => $document->approvedBy?->shortName(),
        ];
    }

    private function signedUrl(): ?string
    {
        $path = $this->resource->path;

        if ($path === null || $path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        // 30 minutes: long enough for a preview in the viewer, short enough that a
        // leaked link is not a standing invitation.
        return URL::temporarySignedRoute(
            'api.knx.documents.download',
            now()->addMinutes(30),
            ['id' => $this->resource->getKey()],
        );
    }

    /**
     * Bytes → the text the office app shows, in the same shape its fixture uses
     * ("4,2 MB", "860 kB", "38 MB"): one decimal for MB and up, whole kB below.
     */
    public static function humanSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        return match (true) {
            $bytes >= 1024 ** 3 => rtrim(rtrim(number_format($bytes / 1024 ** 3, 1, ',', '.'), '0'), ',').' GB',
            $bytes >= 1024 ** 2 => rtrim(rtrim(number_format($bytes / 1024 ** 2, 1, ',', '.'), '0'), ',').' MB',
            default => round($bytes / 1024).' kB',
        };
    }
}
