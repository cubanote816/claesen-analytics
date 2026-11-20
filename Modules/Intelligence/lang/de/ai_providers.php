<?php

// CLA-714: AI providers screen.
return [
    'navigation_label' => 'KI-Anbieter',
    'title' => 'KI-Anbieter',
    'sections' => [
        'keys' => 'API-Schlüssel',
        'keys_desc' => 'Die Schlüssel bleiben in der Serverkonfiguration (.env). Diese Seite zeigt oder speichert sie nie.',
    ],
    'tasks' => [
        'lead_summary' => 'Lead-Zusammenfassung (Hot Leads)',
    ],
    'fields' => [
        'provider' => 'Anbieter',
        'model' => 'Modell',
        'use_configuration' => 'Konfiguration verwenden',
    ],
    'actions' => [
        'test' => 'Testen',
        'save' => 'Speichern',
    ],
    'notifications' => [
        'saved_title' => 'KI-Einstellungen gespeichert',
        'save_failed_title' => 'KI-Einstellungen nicht gespeichert',
        'test_ok_title' => 'Der Anbieter hat geantwortet',
        'test_ok_body' => ':provider · :model · :ms ms',
        'test_failed_title' => 'Der Test ist fehlgeschlagen',
    ],
    'effective' => 'In Verwendung: :provider · :model (:source)',
    'effective_invalid' => 'Die aktuelle Einstellung ist ungültig: :reason',
    'sources' => [
        'screen' => 'auf diesem Bildschirm gewählt',
        'task_config' => 'Aufgabenkonfiguration',
        'default_config' => 'Standardkonfiguration',
    ],
    'keys' => [
        'configured' => 'eingerichtet',
        'missing' => 'nicht eingerichtet',
    ],
];
