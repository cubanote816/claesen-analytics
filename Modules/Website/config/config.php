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
];
