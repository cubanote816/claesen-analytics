<?php

return [
    'navigation_label' => 'Mirror synchronisatiestatus',
    'title' => 'Mirror synchronisatiestatus',
    'description' => 'De mirror bevat een lokale kopie van de CAFCA ERP-gegevens die in het paneel worden gebruikt (Safety, Performance, FieldOps). Dit wordt elke dag automatisch om 04:00 vernieuwd, en kan hier ook handmatig worden vernieuwd.',
    'labels' => [
        'last_sync' => 'Laatste geslaagde synchronisatie',
        'never_synced' => 'Nog nooit gesynchroniseerd',
        'triggered_by_scheduled' => 'Automatisch (dagelijkse planning)',
        'triggered_by_manual' => 'Handmatig, door :name',
        'duration' => 'Duur',
        'recent_runs' => 'Recente uitvoeringen',
        'error' => 'Fout',
    ],
    'status' => [
        'running' => 'Bezig',
        'completed' => 'Voltooid',
        'failed' => 'Mislukt',
    ],
    'actions' => [
        'refresh_now' => 'Nu vernieuwen',
        'sync_employees' => 'Werknemers synchroniseren',
        'sync_clients' => 'Klanten synchroniseren',
        'sync_complexes' => 'Complexen synchroniseren',
        'sync_all_manual' => 'Alles synchroniseren (werknemers + klanten + complexen)',
    ],
    'tasks' => [
        'employees' => 'Werknemers',
        'clients' => 'Klanten',
        'complexes' => 'Complexen',
        'all' => 'Werknemers, klanten en complexen',
    ],
    'notifications' => [
        'queued_title' => 'Synchronisatie in wachtrij',
        'queued_body' => 'De vernieuwing draait op de achtergrond — deze pagina werkt zichzelf automatisch bij.',
        'already_running_title' => 'Synchronisatie loopt al',
        'already_running_body' => 'Er loopt al een synchronisatie. Wacht tot deze klaar is voordat u een nieuwe start.',
        'failed_title' => 'Synchronisatie kon niet worden gestart',
        'manual_sync_done_title' => 'Synchronisatie voltooid',
        'manual_sync_done_body' => ':task succesvol gesynchroniseerd.',
        'manual_sync_failed_title' => 'Synchronisatie mislukt',
    ],
];
