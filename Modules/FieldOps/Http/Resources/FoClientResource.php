<?php

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Services\FieldOpsTenantService;

class FoClientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'street' => $this->street,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'language' => $this->language,
            'complexes_count' => $this->whenCounted('complexes'),
            // CLA-556: the AUTHENTICATED ACTOR's own capability over this
            // FoClient, not a property of the client itself — lets the
            // client-portal frontend discover whether to offer contact
            // management without first calling an endpoint that would 403
            // without it (GET/PATCH .../contacts, gated by the same check).
            'can_manage_contacts' => $request->user()
                ? app(FieldOpsTenantService::class)->canManageContacts($this->resource, $request->user())
                : false,
        ];
    }
}
