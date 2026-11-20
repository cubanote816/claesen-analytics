<?php

return [
    'name' => 'Intelligence',

    /*
     * CLA-714: AI text generation. Provider and model per task; a task
     * without its own provider uses "default". Only models listed under
     * "providers" are accepted. API keys stay in config/services.php.
     */
    'ai' => [
        'timeout' => (int) env('AI_TIMEOUT_SECONDS', 30),

        'default' => [
            'provider' => env('AI_DEFAULT_PROVIDER', 'gemini'),
            'model' => env('AI_DEFAULT_MODEL', 'gemini-2.5-flash'),
        ],

        'tasks' => [
            'lead_summary' => [
                'provider' => env('AI_LEAD_SUMMARY_PROVIDER'),
                'model' => env('AI_LEAD_SUMMARY_MODEL'),
            ],
        ],

        'providers' => [
            'gemini' => [
                'label' => 'Google Gemini',
                'models' => ['gemini-2.5-flash', 'gemini-2.5-pro'],
            ],
            'anthropic' => [
                'label' => 'Anthropic Claude',
                'models' => ['claude-sonnet-5', 'claude-sonnet-5-5', 'claude-haiku-5-5', 'claude-opus-5-5'],
            ],
        ],
    ],
];
