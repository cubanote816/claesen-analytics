<?php

namespace Modules\Knx\Services;

use Modules\Knx\Models\KnxClient;

/**
 * Creating a client (K2, docs/BACKEND-API.md §4.7). The reads stay in the
 * controller; the write is delegated here like every other office write in
 * this module.
 */
class ClientService
{
    /**
     * The tenant trait fills `organization_id` from the session's organization:
     * the caller never sends it.
     *
     * @param  array<string, mixed>  $attributes  the validated payload
     */
    public function create(array $attributes): KnxClient
    {
        return KnxClient::create($attributes);
    }
}
