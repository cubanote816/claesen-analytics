<?php

// CLA-714: AI providers screen.
return [
    'navigation_label' => 'AI-providers',
    'title' => 'AI-providers',
    'sections' => [
        'keys' => 'API-sleutels',
        'keys_desc' => 'De sleutels blijven in de serverconfiguratie (.env). Deze pagina toont of bewaart ze nooit.',
    ],
    'tasks' => [
        'lead_summary' => 'Leadsamenvatting (hot leads)',
    ],
    'fields' => [
        'provider' => 'Provider',
        'model' => 'Model',
        'use_configuration' => 'Configuratie gebruiken',
    ],
    'actions' => [
        'test' => 'Testen',
        'save' => 'Opslaan',
    ],
    'notifications' => [
        'saved_title' => 'AI-instellingen opgeslagen',
        'save_failed_title' => 'AI-instellingen niet opgeslagen',
        'test_ok_title' => 'De provider heeft geantwoord',
        'test_ok_body' => ':provider · :model · :ms ms',
        'test_failed_title' => 'De test is mislukt',
    ],
    'effective' => 'In gebruik: :provider · :model (:source)',
    'effective_invalid' => 'De huidige instelling is ongeldig: :reason',
    'sources' => [
        'screen' => 'gekozen op dit scherm',
        'task_config' => 'taakconfiguratie',
        'default_config' => 'standaardconfiguratie',
    ],
    'keys' => [
        'configured' => 'ingesteld',
        'missing' => 'niet ingesteld',
    ],
];
