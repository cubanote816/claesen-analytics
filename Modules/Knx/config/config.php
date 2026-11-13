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
    | Demo fixture confirmation
    |--------------------------------------------------------------------------
    |
    | KnxDemoSeeder is the demo fixture: it REPLACES the tenant's KNX data on
    | every run (children first), so it is only safe while the database holds no
    | real work. It refuses to run outside the test suite unless this is
    | explicitly enabled, so it can never fire by reflex against a live database
    | (CLA-632). Set KNX_DEMO_SEED_CONFIRM=true — and only when you actually mean
    | to wipe the tenant.
    |
    | Read through config, never `env()` at runtime: this file is evaluated when
    | the config is built, which is what makes it survive `config:cache`
    | (CLA-532).
    |
    */
    'demo_seed_confirm' => env('KNX_DEMO_SEED_CONFIRM', false),

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
    | Veld public host
    |--------------------------------------------------------------------------
    |
    | Same idea for the field app: it shows its own host on the loading screen.
    |
    */
    'veld_domain' => env('KNX_VELD_DOMAIN', 'veld.electrobertels.be'),

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

    /*
    |--------------------------------------------------------------------------
    | Plan downloads (BACKEND-API-VELD.md §2)
    |--------------------------------------------------------------------------
    |
    | How long the signed URL of a plan stays valid. A week on purpose: the field
    | app downloads the drawing once and opens it on site without a connection, so
    | the office viewer's 30 minutes would be gone before the technician arrives.
    | It is still a signature with an expiry, never a public file.
    |
    */
    'plans' => [
        'url_minutes' => env('KNX_PLAN_URL_MINUTES', 60 * 24 * 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Office mailbox for notifications (KNX-54)
    |--------------------------------------------------------------------------
    |
    | The shared address every operational notice is also sent to, next to the project
    | lead. A single configurable inbox instead of a role list: the office decides who
    | reads it at the mail server, not in this code.
    |
    */
    'office_mailbox' => env('KNX_OFFICE_MAILBOX', 'kantoor@electrobertels.be'),

    /*
    |--------------------------------------------------------------------------
    | Plan analysis by an AI provider (KNX-48, plano IA)
    |--------------------------------------------------------------------------
    |
    | A provider PROPOSES group roles and spaces for one plan page; the geometry is
    | always rebuilt from the PDF's own vectors in Kantoor. Nothing is ever sent unless
    | a person authorized that document for that provider (knx_plan_ai_authorizations).
    |
    | - `synthetic`: answers from synthetic examples, never leaves the server, and only
    |   runs in the local and testing environments.
    | - `openai`: Responses API adapter. Switched OFF and without a model by default;
    |   enabling it needs the activation checklist (credentials and models, retention
    |   terms, budget) done first. The credential is an API key read here; a future
    |   "Sign in with ChatGPT" plan token would be another credential source.
    |
    | `budget` is enforced by the backend for EXTERNAL providers: calls per day (0 = none
    | allowed), output tokens per call, retries and the request timeout.
    |
    */
    'plan_analysis' => [
        'schema_version' => 'plan-analysis/1',
        'providers' => [
            'synthetic' => [
                'external' => false,
                'enabled' => (bool) env('KNX_PLAN_ANALYSIS_SYNTHETIC', true),
            ],
            'openai' => [
                'external' => true,
                'enabled' => (bool) env('KNX_PLAN_ANALYSIS_OPENAI_ENABLED', false),
                'model' => env('KNX_PLAN_ANALYSIS_OPENAI_MODEL'),
                'base_url' => env('KNX_PLAN_ANALYSIS_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'api_key' => env('KNX_PLAN_ANALYSIS_OPENAI_API_KEY'),
                'image_detail' => env('KNX_PLAN_ANALYSIS_OPENAI_IMAGE_DETAIL', 'high'),
            ],
        ],
        'budget' => [
            'max_calls_per_day' => (int) env('KNX_PLAN_ANALYSIS_MAX_CALLS_PER_DAY', 0),
            'max_output_tokens' => (int) env('KNX_PLAN_ANALYSIS_MAX_OUTPUT_TOKENS', 4000),
            'max_retries' => (int) env('KNX_PLAN_ANALYSIS_MAX_RETRIES', 0),
            'timeout_seconds' => (int) env('KNX_PLAN_ANALYSIS_TIMEOUT_SECONDS', 60),
            'max_image_bytes' => (int) env('KNX_PLAN_ANALYSIS_MAX_IMAGE_BYTES', 8 * 1024 * 1024),
        ],
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
