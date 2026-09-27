<?php

return [
    'name' => 'Website',

    /*
    |--------------------------------------------------------------------------
    | Consultation notification recipient
    |--------------------------------------------------------------------------
    | CLA-532: internal address that receives the "new consultation request"
    | e-mail. Read via config() so it survives config:cache (was a hardcoded
    | address before). Empty / null => the notification is skipped and a
    | warning is logged; the consultation itself is still persisted (HTTP 201).
    */
    'consultation_notification_email' => env('WEBSITE_CONSULTATION_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Public API locale policy (CLA-611, gap G9 — site-scoped)
    |--------------------------------------------------------------------------
    | Sites listed here serve the public API STRICTLY: a field without a
    | translation in the requested locale resolves to `null` — never Dutch,
    | never English (Modules\Website\Services\PublicLocalePolicy). Claesen
    | stays OFF this list on purpose: its tolerant locale→nl→en fallback is
    | frozen by PortfolioApiTest and its live Astro frontend may depend on
    | it. Opting a new site in is a config change, not a migration.
    */
    'public_api' => [
        'strict_locale_site_keys' => ['electrobertels'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Site settings whitelist (CLA-469)
    |--------------------------------------------------------------------------
    | Modules\Website\Models\SiteSetting only accepts a `key` present here —
    | never a DB enum, so adding a setting is a config change, not a
    | migration (same reasoning as Modules\Analytics\Enums\EventName). The
    | value tells the model/API how to read+serialize the JSON `value`
    | column: 'translatable' is a locale-keyed object handled via
    | Spatie\Translatable\HasTranslations, 'text' is a single scalar
    | string, 'json' is an arbitrary array (e.g. social_links).
    */
    'site_settings' => [
        'allowed_keys' => [
            'hours' => 'translatable',
            'phone' => 'text',
            'email' => 'text',
            'address' => 'text',
            'social_links' => 'json',

            // CLA-479 (dynamic part) — company facts & opening hours for the
            // Electro Bertels frontend (backend-requirements.md §7.2). The
            // pre-existing Claesen keys above are UNTOUCHED — adding keys is
            // additive; no existing key's type or serialization changes.
            // 'address' deliberately stays a verbatim Claesen string; the
            // structured variant for Electro Bertels is the separate
            // 'address_structured' key below (two representations of one
            // fact, kept explicit in the Filament UI instead of silent in
            // the DB — approver decision 2A, 2026-09-27).
            'legal_name' => 'text',
            'founded_year' => 'json',
            'phone_display' => 'text',
            'phone_tel' => 'text',
            'whatsapp_display' => 'text',
            'whatsapp_url' => 'text',
            'address_structured' => 'json',
            'address_country' => 'translatable',
            'maps_embed_url' => 'text',
            'maps_directions_url' => 'text',
            'maps_consent_mode' => 'text',
            'vat_number' => 'text',
            'opening_hours' => 'json',
            'contact_consent_version' => 'text',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention policy (F4/CLA-476)
    |--------------------------------------------------------------------------
    | Global defaults for Modules\Website\Services\RetentionService — a
    | Site's own Organization::retention_policy JSON (D7, populated by this
    | ticket for the first time — inert scaffolding since CLA-458/P1) can
    | override 'spam_days'/'closed_anonymize_days' per organization.
    |
    | 'enabled' gates the destructive part of the mechanism (real delete/
    | anonymize) behind an explicit opt-in — off by default in every
    | environment, matching this program's established D4-style pattern.
    | The ticket's own acceptance criterion ("validación jurídica belga
    | marcada como requisito de lanzamiento") is exactly why: the command
    | and its tests exist and are correct, but nothing destructive runs
    | against real data until that legal review clears it and this flag
    | is turned on. --dry-run always works regardless of this flag, so an
    | admin can preview the effect before enabling it for real.
    */
    'retention' => [
        'enabled' => (bool) env('WEBSITE_RETENTION_ENABLED', false),
        // Spam carries no legitimate business value — short retention,
        // hard delete (cascades to activities/reminders/notifications/
        // email deliveries via their own cascadeOnDelete FKs).
        'spam_days' => (int) env('WEBSITE_RETENTION_SPAM_DAYS', 30),
        // Closed leads: PII anonymized after this many days: name/email/
        // phone/company/internal_notes/tags scrubbed, aggregate fields
        // (status/type/project_type/timestamps) kept for reporting.
        'closed_anonymize_days' => (int) env('WEBSITE_RETENTION_CLOSED_ANONYMIZE_DAYS', 730),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public intake hardening (F4/CLA-475)
    |--------------------------------------------------------------------------
    | Modules\Website\Services\IntakeSpamGuard and the /consultations +
    | /contact-email route middleware read this. See config('services.turnstile')
    | for the separate Cloudflare Turnstile credentials/enforcement flag.
    */
    'intake_hardening' => [
        // Hard cap on the free-text message field — this table has never
        // had one (the original migration left `message` unbounded text).
        'message_max_length' => (int) env('WEBSITE_INTAKE_MESSAGE_MAX_LENGTH', 5000),
        // Name of the hidden form field real visitors never fill in. Kept
        // out of docs/api/website-v1-openapi.yaml on purpose — the exact
        // value is only meant to reach the one frontend that renders it.
        'honeypot_field' => env('WEBSITE_INTAKE_HONEYPOT_FIELD', 'website_url'),
        // Applied to both public intake routes (Modules/Website/Routes/api.php).
        // Laravel's default ThrottleRequests keys by IP for an unauthenticated
        // request, matching the "por IP" criterion directly.
        'rate_limit' => [
            'max_attempts' => (int) env('WEBSITE_INTAKE_RATE_LIMIT_MAX', 10),
            'decay_minutes' => (int) env('WEBSITE_INTAKE_RATE_LIMIT_DECAY', 1),
        ],
        // Modules\Website\Console\Commands\CheckIntakeAbuseAlertsCommand:
        // a site with >= 'threshold' recorded spam attempts (honeypot hits
        // + failed Turnstile checks — never legitimate throttled requests,
        // those carry no evidence of intent) within the trailing
        // 'window_minutes' gets one alert per site per hour.
        'abuse_alert' => [
            'window_minutes' => (int) env('WEBSITE_INTAKE_ABUSE_WINDOW_MINUTES', 60),
            'threshold' => (int) env('WEBSITE_INTAKE_ABUSE_THRESHOLD', 20),
        ],
        // CLA-484 (approver decision 1A): sites whose intake REQUIRES
        // `consent_version` (version of the accepted consent text). Resolved
        // from the request's site key — never a hardcoded id check. Claesen
        // stays off this list: its live form doesn't send the field and its
        // payload/behaviour stays byte-identical.
        'consent_version_required_site_keys' => ['electrobertels'],
    ],
];
