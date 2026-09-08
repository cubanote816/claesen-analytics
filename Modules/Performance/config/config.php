<?php

return [
    'name'             => 'Performance',
    'ai_insight_locale' => env('PERFORMANCE_AI_LOCALE', 'nl'),

    /*
    |--------------------------------------------------------------------------
    | Watchdog
    |--------------------------------------------------------------------------
    | CLA-532: recipients / thresholds read via config() (config:cache-safe),
    | never env() at runtime. No default recipient address — an unset value is
    | a deliberate "no recipient configured" signal each caller handles
    | (command returns FAILURE / warning + no send, alert flag left unchanged).
    */
    'watchdog' => [
        'report_email' => env('WATCHDOG_REPORT_EMAIL'),
        'vanguard_email' => env('WATCHDOG_VANGUARD_EMAIL'),
        'immediate_threshold' => (int) env('WATCHDOG_IMMEDIATE_THRESHOLD', 20000),
        'sync_years_back' => (int) env('WATCHDOG_SYNC_YEARS_BACK', 5),
    ],
];
