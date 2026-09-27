<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxConflict;

/**
 * `ReportIssueResult` of the Veld contract: two keys, and both are needed.
 *
 * `clientId` goes back because that is what lets the app mark its own queued item as
 * delivered without keeping a second index of what it sent.
 *
 * @property-read KnxConflict $resource
 */
class FieldIssueResultResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'clientId' => $this->resource->client_id,
        ];
    }
}
