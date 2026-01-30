<?php

return [
    'label' => 'Medewerker',
    'plural_label' => 'Medewerkers',
    'navigation_label' => 'Medewerkers',
    'navigation_group' => 'Personeel',
    'placeholders' => [
        'no_function' => 'Geen functie opgegeven',
        'total_hours' => '--- u',
        'efficiency' => '--%',
        'projects_count' => '--',
        'ai_insights_loading' => 'AI-analyse wordt uitgevoerd...',
        'project_timeline_loading' => 'Recente projecten laden...',
    ],

    'navigation' => [
        'details' => 'Details',
        'edit' => 'Bewerken',
        'performance' => 'Prestaties',
    ],

    'fields' => [
        'id' => 'ID',
        'name' => 'Naam',
        'email' => 'E-mail',
        'mobile' => 'GSM',
        'phone' => 'Telefoon',
        'is_active' => 'Status',
        'job_function' => 'Functie',
        'function_default' => 'Algemeen',
        'avatar' => 'Avatar',
        'address' => 'Adres',
        'street' => 'Straat',
        'zip' => 'Postcode',
        'city' => 'Stad',
        'country' => 'Land',
        'notes' => 'Notities',
        'personal_information' => 'Persoonlijke Informatie',
        'contact_details' => 'Contactgegevens',
    ],

    'status' => [
        'active' => 'Actief',
        'inactive' => 'Inactief',
    ],

    'actions' => [
        'performance' => 'Prestaties',
        'edit' => [
            'label' => 'Bewerken',
        ],
        'sync' => [
            'label' => 'Sincronizar SAP/ERP',
            'notification' => [
                'success' => 'Synchronisatie voltooid: :created nieuw, :updated bijgewerkt.',
                'error' => 'Er is een fout opgetreden tijdens de synchronisatie.',
            ],
            'command' => [
                'description' => 'Synchronizeer techniekers vanuit Legacy SQL Server naar lokale MySQL',
                'starting' => 'Starten van synchronisatie van techniekers...',
                'up_to_date' => 'Alle records zijn al up-to-date.',
                'success' => 'Synchronisatie succesvol voltooid.',
                'failed' => 'Synchronisatie mislukt: :error',
                'table' => [
                    'created' => 'Aangemaakt',
                    'updated' => 'Bijgewerkt',
                    'errors' => 'Fouten',
                ],
            ],
        ],
        'analyze' => [
            'label' => 'AI Analyse Starten',
            'confirm' => [
                'title' => 'Nieuwe analyse starten?',
                'body' => 'Dit zal de prestatiegegevens van de laatste 6 maanden opnieuw ophalen en analyseren met AI. Dit kan even duren.',
            ],
            'notification' => [
                'success' => 'Analyse gestart op de achtergrond. Vernieuw de pagina over enkele ogenblikken.',
            ],
        ],
    ],
    'sections' => [
        'business_card' => 'Visitekaartje',
        'performance_dashboard' => 'Prestaties Dashboard',
        'ai_insights' => 'AI Analyse & Archetype',
        'project_timeline' => 'Project Tijdlijn',
        'watchdog_alerts' => 'WATCHDOG ALERT',
        'watchdog_description' => 'Kritieke meldingen voor dit profiel.',
    ],

    'insights' => [
        'archetype' => 'Technicus Arquetype',
        'burnout_risk' => 'Burnout Risico',
        'efficiency_trend' => 'Efficiëntie Trend',
        'manager_insight' => 'Manager Inzicht',
        'last_audited' => 'Laatst geanalyseerd',
        'status' => [
            'stable' => 'Stabiel',
            'increasing' => 'Stijgend',
            'decreasing' => 'Dalend',
        ],
    ],

    'messages' => [
        'watchdog_warning' => 'Kritieke prestatie-afwijking gedetecteerd. Controleer de projectdetails onmiddellijk.',
    ],

    'stats' => [
        'total_hours' => 'Totaal Gewerkt',
        'efficiency' => 'Efficiëntie',
        'projects_count' => 'Actieve Projecten',
    ],
];
