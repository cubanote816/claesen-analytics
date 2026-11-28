<?php

// CLA-714: AI providers screen.
return [
    'navigation_label' => 'AI providers',
    'title' => 'AI providers',
    'sections' => [
        'keys' => 'API keys',
        'keys_desc' => 'Keys stay in the server configuration (.env). This page never shows or stores them.',
    ],
    'tasks' => [
        'lead_summary' => 'Lead summary (hot leads)',
    ],
    'fields' => [
        'provider' => 'Provider',
        'model' => 'Model',
        'use_configuration' => 'Use the configuration',
    ],
    'actions' => [
        'test' => 'Test',
        'save' => 'Save',
    ],
    'notifications' => [
        'saved_title' => 'AI settings saved',
        'save_failed_title' => 'AI settings not saved',
        'test_ok_title' => 'The provider answered',
        'test_ok_body' => ':provider · :model · :ms ms',
        'test_failed_title' => 'The test failed',
    ],
    'effective' => 'In use: :provider · :model (:source)',
    'effective_invalid' => 'The current setting is not valid: :reason',
    'sources' => [
        'screen' => 'chosen on this screen',
        'task_config' => 'task configuration',
        'default_config' => 'default configuration',
    ],
    'keys' => [
        'configured' => 'configured',
        'missing' => 'not configured',
    ],
];
