<?php

return [
    'navigation_label' => 'Status der Spiegel-Synchronisierung',
    'title' => 'Status der Spiegel-Synchronisierung',
    'description' => 'Der Spiegel hält eine lokale Kopie der CAFCA-ERP-Daten vor, die im gesamten Panel verwendet werden (Safety, Performance, FieldOps). Er wird täglich um 04:00 Uhr automatisch aktualisiert und kann hier auch manuell aktualisiert werden.',
    'labels' => [
        'last_sync' => 'Letzte erfolgreiche Synchronisierung',
        'never_synced' => 'Noch nie synchronisiert',
        'triggered_by_scheduled' => 'Automatisch (täglicher Zeitplan)',
        'triggered_by_manual' => 'Manuell, durch :name',
        'duration' => 'Dauer',
        'recent_runs' => 'Letzte Durchläufe',
        'error' => 'Fehler',
    ],
    'status' => [
        'running' => 'Läuft',
        'completed' => 'Abgeschlossen',
        'failed' => 'Fehlgeschlagen',
    ],
    'actions' => [
        'refresh_now' => 'Jetzt aktualisieren',
        'sync_employees' => 'Mitarbeiter synchronisieren',
        'sync_clients' => 'Kunden synchronisieren',
        'sync_complexes' => 'Anlagen synchronisieren',
        'sync_all_manual' => 'Alles synchronisieren (Mitarbeiter + Kunden + Anlagen)',
    ],
    'tasks' => [
        'employees' => 'Mitarbeiter',
        'clients' => 'Kunden',
        'complexes' => 'Anlagen',
        'all' => 'Mitarbeiter, Kunden und Anlagen',
    ],
    'notifications' => [
        'queued_title' => 'Synchronisierung eingereiht',
        'queued_body' => 'Die Aktualisierung läuft im Hintergrund — diese Seite wird automatisch aktualisiert.',
        'already_running_title' => 'Synchronisierung läuft bereits',
        'already_running_body' => 'Eine Synchronisierung läuft bereits. Warten Sie, bis sie beendet ist, bevor Sie eine weitere starten.',
        'failed_title' => 'Synchronisierung konnte nicht eingereiht werden',
        'manual_sync_done_title' => 'Synchronisierung abgeschlossen',
        'manual_sync_done_body' => ':task erfolgreich synchronisiert.',
        'manual_sync_failed_title' => 'Synchronisierung fehlgeschlagen',
    ],
];
