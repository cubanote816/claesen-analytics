<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Knx\Models\KnxExport;

/**
 * `ExportRecord` of docs/BACKEND-API.md §3.
 *
 * It also carries `downloadUrl`, which §4.9 recommends as an extension: the
 * contract's own type has no link, so the office app could only ever show that a
 * report exists, never open it. Null until the file is actually there.
 *
 * @property-read KnxExport $resource
 */
class ExportRecordResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $export = $this->resource;

        return [
            'id' => (string) $export->getKey(),
            'type' => $export->type,
            'projectCode' => $export->project?->code,
            'projectName' => $export->project?->name,
            'createdAt' => $export->created_at->toIso8601ZuluString(),
            'downloadUrl' => $this->downloadUrl(),
        ];
    }

    private function downloadUrl(): ?string
    {
        $path = $this->resource->path;

        if ($this->resource->status !== KnxExport::STATUS_READY || $path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'api.knx.exports.download',
            now()->addMinutes(30),
            ['id' => $this->resource->getKey()],
        );
    }
}
