<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Modules\Knx\Models\KnxDocument;

/**
 * `Plan` of the Veld contract.
 *
 * `url` is a **signed** link, and it is deliberately long-lived: the app downloads
 * the drawing once and opens it on site with no connection, so the office viewer's
 * 30 minutes would expire before the technician gets there. It is still a
 * signature with an expiry, never a public file.
 *
 * `sizeBytes` is the raw number (the office contract sends human text instead,
 * "4,2 MB"): the app formats it and needs the number to do so.
 *
 * @property-read KnxDocument $resource
 */
class FieldPlanResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'id' => (string) $document->getKey(),
            'projectCode' => $document->project->code,
            'name' => $document->name,
            'kind' => $document->kind,
            'revision' => $document->revision,
            'mimeType' => $document->resolvedMimeType(),
            'url' => $this->signedUrl(),
            'sizeBytes' => (int) ($document->size_bytes ?? 0),
            'pages' => (int) ($document->pages ?? 1),
        ];
    }

    private function signedUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.knx.documents.download',
            now()->addMinutes((int) config('knx.plans.url_minutes')),
            ['id' => $this->resource->getKey()],
        );
    }
}
