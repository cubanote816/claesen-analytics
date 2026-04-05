<?php

return [
    'model_label' => 'Prospect',
    'plural_model_label' => 'Prospecten',
    'navigation_group' => 'Groei & Acquisitie',

    'sections' => [
        'club_info' => 'Club Informatie',
        'marketing_targets' => 'Marketing Doelwitten',
        'sync_details' => 'Sync Details',
        'logs' => 'Logs / Events',
        'logs_desc' => 'Gedetailleerde weergave van de gebeurtenissen tijdens de uitvoering.',
        'mailing_details' => 'Mailing Details',
        'mail_details' => 'Mailing Details',
        'snapshot' => 'Snapshot',
        'snapshot_desc' => 'De exacte inhoud van de verzonden e-mail.',
        'campaign_summary' => 'Campagne Samenvatting',
        'recipients' => 'Ontvangers',
    ],

    'fields' => [
        'name' => 'Naam van de Club',
        'region' => 'Regio',
        'federation' => 'Federatie',
        'language' => 'Taal',
        'contact_person' => 'Secretaris / Contactpersoon',
        'channel' => 'Kanaal',
        'website' => 'Website',
        'vat_number' => 'BTW Nummer',
        'cafca_id' => 'CAFCA Relatie ID',
        'locations' => 'Locaties',
        'contact_type' => 'Type Contact',
        'email' => 'E-mail',
        'phone' => 'Telefoonnummer',
        'address' => 'Adres',
        'logo' => 'Logo',
        'has_email' => 'Heeft E-mailadres',
        'locations_count' => 'Aantal Locaties',
        'command' => 'Commando',
        'status' => 'Status',
        'started_by' => 'Gestart door',
        'items' => 'Items',
        'started_at' => 'Gestart op',
        'finished_at' => 'Klaar op',
        'total_items' => 'Totaal Items processed',
        'prospect' => 'Prospect',
        'template' => 'Template / Campagne',
        'sent_at' => 'Verzonden op',
        'error_message' => 'Foutmelding',
        'subject' => 'Onderwerp',
        'body' => 'Inhoud',
        'type_sport' => 'Type Sport',
        'description' => 'Beschrijving / Doelstelling',
        'total_count' => 'Totaal',
        'success_count' => 'Succes',
        'failed_count' => 'Fout',
        'skipped_count' => 'Overgeslagen',
        'unsubscribed_at' => 'Inschrijvingsstatus',
    ],

    'options' => [
        'contact_types' => [
            'headquarters' => 'Hoofdkantoor',
            'stadium' => 'Stadion',
            'venue_name' => 'Locatie Naam',
            'club_house' => 'Clubhuis',
            'contact_person' => 'Contactpersoon',
            'other' => 'Andere',
        ],
        'languages' => [
            'nl' => 'Nederlands',
            'fr' => 'Frans',
            'en' => 'Engels',
        ],
        'status' => [
            'running' => 'Bezig...',
            'completed' => 'Voltooid',
            'failed' => 'Mislukt',
            'sent' => 'Verzonden',
            'skipped' => 'Overgeslagen (Geen e-mail)',
            'active' => 'Actief',
            'unsubscribed' => 'Uitgeschreven',
            'all' => 'Alle',
        ],
        'sport_types' => [
            'football_club' => 'Voetbal',
            'athletics_club' => 'Atletiek',
            'tennis_padel_club' => 'Tennis & Padel',
            'hockey_club' => 'Hockey',
        ],
    ],

    'actions' => [
        'execute_campaign' => [
            'label' => 'Start Mailing Campagne',
            'form' => [
                'template' => 'Kies E-mail Sjabloon',
                'description' => 'Campagne Beschrijving',
                'description_placeholder' => 'Bijv: Opvolging beurs of Introductie nieuwe catalogus...',
            ],
        ],
        'sync_master' => [
            'label' => 'Sync Alles (Master)',
        ],
        'individual_sync' => [
            'label' => 'Individuele Sync',
            'rbfa' => 'Sync RBFA (Voetbal)',
            'lbfa' => 'Sync LBFA (Atletiek FR)',
            'val' => 'Sync VAL (Atletiek NL)',
            'hockey' => 'Sync Hockey (VHL/LFH)',
            'tpv' => 'Sync Tennis & Padel (TPV)',
            'aft' => 'Sync AFT (Tennis FR)',
        ],
        'apply_filters' => 'Filters Toepassen',
        'mark_failed' => [
            'label' => 'Als Mislukt Markeren',
        ],
        'mark_completed' => [
            'label' => 'Als Voltooid Markeren',
        ],
    ],

    'notifications' => [
        'no_prospects_selected' => [
            'title' => 'Geen prospecten geselecteerd',
            'body' => 'Selecteer minstens één prospect om een mailing te starten.',
        ],
        'campaign_started' => [
            'title' => 'Campagne Gestart',
            'body' => 'De e-mails worden op de achtergrond verzonden met het gekozen sjabloon.',
        ],
        'master_sync_started' => [
            'title' => 'Master Sync Gestart',
            'body' => 'Alle federaties worden nu sequentieel op de achtergrond gesynchroniseerd.',
        ],
        'sync_started' => [
            'title' => 'Synchronisatie Gestart',
            'body' => 'De taak :command wordt uitgevoerd in de achtergrond.',
        ],
        'manually_failed' => [
            'title' => 'Status bijgewerkt naar Mislukt',
        ],
        'manually_completed' => [
            'title' => 'Status bijgewerkt naar Voltooid',
        ],
        'no_emails_found' => [
            'title' => 'Geen e-mailadressen gevonden',
            'body' => 'De geselecteerde prospecten hebben geen locaties met geregistreerde e-mailadressen.',
        ],
        'error_mailer' => 'Fout bij het verzenden van de campagne via de mailer service.',
    ],

    'sync_history' => [
        'model_label' => 'Synchronisatie',
        'plural_model_label' => 'Sincronisatie Beheer',
        'logs' => [
            'master_requested' => 'Master-synchronisatie aangevraagd via het paneel.',
            'manually_failed' => 'De synchronisatie is handmatig als MISLUKT gemarkeerd door een administrator.',
            'manually_completed' => 'De synchronisatie is handmatig als VOLTOOID gemarkeerd door een administrator.',
        ],
    ],

    'mail_log' => [
        'model_label' => 'Ontvanger Log',
        'plural_model_label' => 'Ontvangers',
    ],
    
    'mail_campaign' => [
        'model_label' => 'Mailing Campagne',
        'plural_model_label' => 'Mail History',
    ],

    'defaults' => [
        'region' => 'Vlaanderen',
    ],

    'unsubscribe' => [
        'link' => 'Uitschrijven',
        'text' => 'Wilt u deze e-mails niet meer ontvangen?',
        'success_title' => 'Uitgeschreven',
        'success_body' => 'U bent succesvol uitgeschreven uit onze mailinglijst.',
        'confirmation_button' => 'Bevestig Uitschrijving',
    ],
];
