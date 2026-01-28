<?php

return [
    'label' => 'Medewerker',
    'plural_label' => 'Medewerkers',
    'navigation_label' => 'Medewerkers',
    'navigation_group' => 'Personeel',

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
        'sync' => [
            'label' => 'Sincronizar SAP/ERP',
            'notification' => [
                'success' => 'Synchronisatie voltooid: :created nieuw, :updated bijgewerkt.',
                'error' => 'Er is een fout opgetreden tijdens de synchronisatie.',
            ],
        ],
    ],

    'placeholders' => [
        'total_hours' => '--- u',
        'efficiency' => '--%',
        'projects_count' => '--',
        'ai_insights_loading' => 'AI-analyse wordt uitgevoerd...',
        'project_timeline_loading' => 'Recente projecten laden...',
    ],

    'sections' => [
        'business_card' => 'Visitekaartje',
        'performance_dashboard' => 'Prestaties Dashboard',
        'ai_insights' => 'AI Inzichten',
        'project_timeline' => 'Project Tijdlijn',
    ],

    'stats' => [
        'total_hours' => 'Totaal Gewerkt',
        'efficiency' => 'Efficiëntie',
        'projects_count' => 'Actieve Projecten',
    ],
];
