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
];
