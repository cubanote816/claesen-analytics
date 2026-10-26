<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxVisit;

/**
 * `CloseVisitResult` of the Veld contract: the id and the app's own key.
 *
 * Nothing else comes back because nothing else is needed: the field app keeps its own
 * local record of what it sent, and `clientId` is what lets it mark that record as
 * delivered. A closure is never read back from the phone.
 *
 * @property-read KnxVisit $resource
 */
class FieldVisitResultResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'clientId' => $this->resource->client_id,
        ];
    }
}
