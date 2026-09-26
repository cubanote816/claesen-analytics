<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Knx\Models\KnxEmployee;

/**
 * `Session` of docs/BACKEND-API.md §3: the signed-in office user.
 *
 * The wire shape mixes two things on purpose, and they come from two places:
 *   - who they are (name, initials) and `role` — the *business* role the front
 *     labels ("lead" → "Projectleider") — come from the person row;
 *   - `email` comes from the account, because that is what they sign in with.
 *
 * `id` is the person's id (not the account's): every other reference in the
 * contract points at a person, so a session that identified the account would be
 * the only place with a different identity.
 *
 * @property-read KnxEmployee $resource
 */
class SessionResource extends JsonResource
{
    /**
     * The contract describes the object itself, not an envelope: `GET /me/session`
     * returns `{id, name, initials, role, email, domain}`, and the front reads
     * `name` at the top level.
     */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'initials' => $this->resource->initials,
            'role' => $this->resource->knx_role,
            'email' => $this->resource->user?->email,
            'domain' => config('knx.kantoor_domain'),
        ];
    }
}
