<?php

return [
    'label' => 'Mitarbeiter',
    'plural_label' => 'Mitarbeiter',
    'navigation_label' => 'Mitarbeiter',
    'navigation_group' => 'Personal',
    'placeholders' => [
        'no_function' => 'Keine Funktion angegeben',
        'total_hours' => '--- h',
        'efficiency' => '--%',
        'projects_count' => '--',
        'ai_insights_loading' => 'KI-Analyse läuft...',
        'project_timeline_loading' => 'Aktuelle Projekte werden geladen...',
        'last_6_months' => 'Letzte 6 Monate',
        'last_month' => 'Letzter Monat',
        'this_month' => 'Dieser Monat',
        'last_quarter' => 'Letztes Quartal',
        'last_semester' => 'Letztes Halbjahr',
        'previous_year' => 'Vorjahr',
        'from' => 'Von',
        'to' => 'Bis',
    ],

    'navigation' => [
        'details' => 'Details',
        'edit' => 'Bearbeiten',
        'performance' => 'Leistung',
    ],

    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'email' => 'E-Mail',
        'mobile' => 'Mobil',
        'phone' => 'Telefon',
        'is_active' => 'Status',
        'function_default' => 'Allgemein',
        'job_function' => 'Funktion',
        'avatar' => 'Avatar',
        'address' => 'Adresse',
        'street' => 'Straße',
        'zip' => 'Postleitzahl',
        'city' => 'Ort',
        'country' => 'Land',
        'notes' => 'Notizen',
        'personal_information' => 'Persönliche Informationen',
        'contact_details' => 'Kontaktdaten',
    ],

    'status' => [
        'all' => 'Alle',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'actions' => [
        'performance' => 'Leistung',
        'edit' => [
            'label' => 'Bearbeiten',
        ],
        'sync' => [
            'label' => 'SAP/ERP synchronisieren',
            'notification' => [
                'success' => 'Synchronisierung abgeschlossen: :created neu, :updated aktualisiert.',
                'error' => 'Bei der Synchronisierung ist ein Fehler aufgetreten.',
            ],
            'command' => [
                'description' => 'Techniker aus dem Legacy SQL Server mit dem lokalen MySQL synchronisieren',
                'starting' => 'Synchronisierung der Techniker wird gestartet...',
                'up_to_date' => 'Alle Datensätze sind bereits auf dem neuesten Stand.',
                'success' => 'Synchronisierung erfolgreich abgeschlossen.',
                'failed' => 'Synchronisierung fehlgeschlagen: :error',
                'table' => [
                    'created' => 'Erstellt',
                    'updated' => 'Aktualisiert',
                    'errors' => 'Fehler',
                ],
            ],
        ],
        'analyze' => [
            'label' => 'KI-Analyse starten',
            'confirm' => [
                'title' => 'Neue Analyse starten?',
                'body' => 'Dadurch werden die Leistungsdaten der letzten 6 Monate erneut abgerufen und mit KI analysiert. Das kann einen Moment dauern.',
            ],
            'notification' => [
                'success' => 'Analyse im Hintergrund gestartet. Aktualisieren Sie die Seite in einigen Augenblicken.',
            ],
        ],
    ],
    'sections' => [
        'business_card' => 'Visitenkarte',
        'performance_dashboard' => 'Leistungs-Dashboard',
        'performance_dashboard_desc' => 'Analyse der Stundenverteilung und der Projektaktivität im ausgewählten Zeitraum.',
        'ai_insights' => 'KI-Erkenntnisse & Archetyp',
        'project_timeline' => 'Projektzeitachse',
        'project_timeline_title' => 'PROJEKTZEITACHSE',
        'hours_distribution' => 'STUNDENVERTEILUNG',
        'watchdog_alerts' => 'WATCHDOG-WARNUNG',
        'watchdog_description' => 'Kritische Warnungen für dieses Profil.',
    ],

    'insights' => [
        'archetype' => 'Techniker-Archetyp',
        'burnout_risk' => 'Burnout-Risiko',
        'efficiency_trend' => 'Effizienztrend',
        'current_project' => 'Aktive Projekte (30 T)',
        'manager_insight' => 'Einschätzung der Führungskraft',
        'last_audited' => 'Zuletzt geprüft',
        'status' => [
            'stable' => 'Stabil',
            'increasing' => 'Steigend',
            'decreasing' => 'Sinkend',
        ],
    ],

    'messages' => [
        'watchdog_warning' => 'Kritische Leistungsanomalie erkannt. Prüfen Sie umgehend die Projektdetails.',
    ],

    'stats' => [
        'total_hours' => 'Gesamt gearbeitet',
        'efficiency' => 'Effizienz',
        'projects_count' => 'Aktive Projekte',
    ],

    'empty_state' => [
        'title' => 'Keine Daten verfügbar',
        'description' => 'Für :name wurden im ausgewählten Zeitraum keine erfassten Arbeitsstunden gefunden.',
        'action_this_month' => 'Vormonat anzeigen',
        'action_previous_year' => 'Vorjahr anzeigen',
        'erp_unavailable_title' => 'ERP vorübergehend nicht verfügbar',
        'erp_unavailable_description' => 'Die Verbindung zum Verwaltungssystem wurde unterbrochen. Die Daten können vorübergehend nicht geladen werden.',
        'erp_unavailable_retry' => 'Erneut versuchen',
        'no_activity_title' => 'Keine Aktivität in diesem Zeitraum',
        'no_activity_description' => 'Für :name wurden im ausgewählten Zeitraum keine Arbeitsstunden gefunden. Versuchen Sie einen anderen Zeitraum.',
        'no_activity_suggestion' => 'Letztes Halbjahr anzeigen',
    ],

    'dashboard' => [
        'active_range' => 'AKTIVER ZEITRAUM',
        'global_load' => 'GESAMTAUSLASTUNG',
        'project_showcase' => 'PROJEKTÜBERSICHT',
        'project_insight' => 'Projekterkenntnis',
        'efficiency' => 'Effizienz',
        'verified_match' => 'Geprüfte Übereinstimmung',
        'view_archives' => 'Archiv anzeigen',
        'custom_range' => 'Benutzerdefinierter Zeitraum',
        'loading' => [
            'focus' => 'Fokus der Erkenntnisse',
            'sync' => 'Daten werden synchronisiert...',
            'stage' => 'Phase der Erkenntnisse',
        ],
    ],
];
