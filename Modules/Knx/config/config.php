<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant
    |--------------------------------------------------------------------------
    |
    | The KNX domain belongs to Electro Bertels only. Every row carries an
    | organization_id and every route is behind `organization:{this slug}`
    | (Modules\Core\Http\Middleware\RequireOrganization, CLA-552), so a
    | Claesen token cannot read or write any of it.
    |
    | docs/BACKEND-API.md calls this "company_id"; in this repo the tenant is
    | an Organization (see docs/ai/adr-multi-organization.md), so the API keeps
    | the doc's vocabulary while the schema uses organization_id.
    |
    */
    'organization_slug' => 'electro-bertels',

    /*
    |--------------------------------------------------------------------------
    | Access token lifetime
    |--------------------------------------------------------------------------
    |
    | docs/BACKEND-API.md §7 recommends short-lived access tokens (15-60 min).
    | `expires_in` in the login response is this value in seconds.
    |
    */
    'token_expiry_minutes' => env('KNX_TOKEN_EXPIRY_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Kantoor public host
    |--------------------------------------------------------------------------
    |
    | Returned as `Session.domain` so the office app can show its own host in
    | the sidebar footer. Per ADR D1 the domain is a property of the site, so
    | this is only the fallback for environments where no site row resolves.
    |
    */
    'kantoor_domain' => env('KNX_KANTOOR_DOMAIN', 'kantoor.electrobertels.be'),

    /*
    |--------------------------------------------------------------------------
    | Zone readiness checklist
    |--------------------------------------------------------------------------
    |
    | The eight checks of docs/BACKEND-API-ZONES.md §1.2, in the exact order the
    | UI renders them. `Zone.status` is derived from these on every read and
    | every write — the order matters because blockingReason/blockedBy come from
    | the FIRST failed check.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Server-sent events (§6)
    |--------------------------------------------------------------------------
    |
    | How long one `/events` connection may live before closing politely, and how
    | often it looks for something new. The cap exists so a stream cannot hold a PHP
    | worker hostage: the browser reconnects by itself.
    |
    */
    'events' => [
        'stream_seconds' => env('KNX_EVENTS_STREAM_SECONDS', 55),
        'poll_seconds' => env('KNX_EVENTS_POLL_SECONDS', 2),
    ],

    'zone_check_keys' => [
        'installed',
        'power',
        'bus',
        'device_ids',
        'loads',
        'materials',
        'function_approved',
        'blockers',
    ],

];
