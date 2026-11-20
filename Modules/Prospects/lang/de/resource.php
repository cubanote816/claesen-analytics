<?php

return [
    'model_label' => 'Interessent',
    'plural_model_label' => 'Interessenten',
    'navigation_group' => 'Wachstum & Akquise',

    'tabs' => [
        'real_prospects' => 'Echte Interessenten',
        'testers' => 'Tester (Management)',
        'all' => 'Alle',
    ],

    'sections' => [
        'club_info' => 'Vereinsinformationen',
        'marketing_targets' => 'Marketingziele',
        'sync_details' => 'Synchronisierungsdetails',
        'logs' => 'Protokolle / Ereignisse',
        'logs_desc' => 'Detaillierte Ansicht der Ereignisse während der Ausführung.',
        'mailing_details' => 'Mailing-Details',
        'mail_details' => 'Mailing-Details',
        'snapshot' => 'Snapshot',
        'snapshot_desc' => 'Genauer Inhalt der gesendeten E-Mail.',
        'campaign_summary' => 'Kampagnenübersicht',
        'recipients' => 'Empfänger',
    ],

    'fields' => [
        'name' => 'Vereinsname',
        'region' => 'Region',
        'federation' => 'Verband',
        'language' => 'Sprache',
        'contact_person' => 'Sekretär / Ansprechpartner',
        'channel' => 'Kanal',
        'website' => 'Website',
        'vat_number' => 'USt-IdNr.',
        'cafca_id' => 'CAFCA-Beziehungs-ID',
        'locations' => 'Standorte',
        'contact_type' => 'Kontaktart',
        'contact_name' => 'Kontaktname',
        'email' => 'E-Mail',
        'phone' => 'Telefonnummer',
        'address' => 'Adresse',
        'logo' => 'Logo',
        'has_email' => 'Hat E-Mail-Adresse',
        'locations_count' => 'Standorte',
        'command' => 'Befehl',
        'status' => 'Status',
        'started_by' => 'Gestartet von',
        'items' => 'Einträge',
        'started_at' => 'Gestartet am',
        'finished_at' => 'Beendet am',
        'total_items' => 'Insgesamt verarbeitete Einträge',
        'last_activity' => 'Letzte Aktivität',
        'prospect' => 'Interessent',
        'template' => 'Vorlage / Kampagne',
        'sent_at' => 'Gesendet am',
        'error_message' => 'Fehlermeldung',
        'subject' => 'Betreff',
        'body' => 'Inhalt',
        'type_sport' => 'Sportart',
        'description' => 'Beschreibung / Ziel',
        'total_count' => 'Gesamt',
        'success_count' => 'Erfolgreich',
        'failed_count' => 'Fehlgeschlagen',
        'skipped_count' => 'Übersprungen',
        'unsubscribed_at' => 'Abonnementstatus',
        'is_tester' => 'Tester (Testumgebung)',
        'is_tester_helper' => 'Als Test-Interessenten für das Management markieren',
        'tester' => 'Tester',
    ],

    'options' => [
        'contact_types' => [
            'headquarters' => 'Hauptsitz',
            'stadium' => 'Stadion',
            'venue_name' => 'Name der Spielstätte',
            'club_house' => 'Vereinsheim',
            'contact_person' => 'Ansprechpartner',
            'other' => 'Sonstiges',
        ],
        'languages' => [
            'nl' => 'Niederländisch',
            'fr' => 'Französisch',
            'en' => 'Englisch',
        ],
        'status' => [
            'pending' => 'In Warteschlange...',
            'running' => 'Läuft...',
            'completed' => 'Abgeschlossen',
            'failed' => 'Fehlgeschlagen',
            'sent' => 'Gesendet',
            'skipped' => 'Übersprungen (keine E-Mail)',
            'active' => 'Aktiv',
            'unsubscribed' => 'Abgemeldet',
            'all' => 'Alle',
        ],
        'sport_types' => [
            'football_club' => 'Fußball',
            'athletics_club' => 'Leichtathletik',
            'tennis_padel_club' => 'Tennis & Padel',
            'hockey_club' => 'Hockey',
            'table_tennis_club' => 'Tischtennis',
            'sports_infrastructure' => 'Sportinfrastruktur',
        ],
    ],

    'actions' => [
        'execute_campaign' => [
            'label' => 'Mailing-Kampagne starten',
            'view_campaign' => 'Kampagne anzeigen',
            'form' => [
                'template' => 'E-Mail-Vorlage auswählen',
                'description' => 'Kampagnenbeschreibung',
                'description_placeholder' => 'z. B.: Nachfassen nach der Messe oder Vorstellung des neuen Katalogs...',
            ],
        ],
        'sync_master' => [
            'label' => 'Alles synchronisieren (Master)',
        ],
        'individual_sync' => [
            'label' => 'Einzelne Synchronisierung',
            'rbfa' => 'RBFA synchronisieren (Fußball)',
            'lbfa' => 'LBFA synchronisieren (Leichtathletik FR)',
            'val' => 'VAL synchronisieren (Leichtathletik NL)',
            'hockey' => 'Hockey synchronisieren (VHL/LFH)',
            'tpv' => 'Tennis & Padel synchronisieren (TPV)',
            'aft' => 'AFT synchronisieren (Tennis FR)',
        ],
        'apply_filters' => 'Filter anwenden',
        'mark_failed' => [
            'label' => 'Als fehlgeschlagen markieren',
        ],
        'mark_completed' => [
            'label' => 'Als abgeschlossen markieren',
        ],
        'back_to_list' => 'Zurück zur Liste',
    ],

    'notifications' => [
        'no_prospects_selected' => [
            'title' => 'Keine Interessenten ausgewählt',
            'body' => 'Bitte wählen Sie mindestens einen Interessenten aus, um das Mailing zu starten.',
        ],
        'all_prospects_unsubscribed' => [
            'title' => 'Alle ausgewählten Interessenten sind abgemeldet',
            'body' => 'Keiner der ausgewählten Interessenten kann E-Mails erhalten. Entfernen Sie sie zuerst von der Sperrliste.',
        ],
        'campaign_started' => [
            'title' => 'Versand im Hintergrund',
            'body' => ':count E-Mails werden im Hintergrund gesendet. Sie können weiterarbeiten; wir benachrichtigen Sie, sobald der Versand abgeschlossen ist.',
        ],
        'campaign_sent_to_review' => [
            'title' => 'Kampagne zur Prüfung eingereicht',
            'body' => 'Die Kampagne wurde erstellt und zur Prüfung eingereicht. Ein Genehmiger muss sie genehmigen, bevor sie versendet wird.',
        ],
        'campaign_template_invalid' => [
            'title' => 'Kampagne nicht erstellt',
            'body' => ':reason',
        ],
        'partial_skip' => [
            'title' => 'Kampagne gestartet',
            'body' => 'Achtung: :unsubscribed der :total ausgewählten Interessenten sind abgemeldet und werden übersprungen.',
        ],
        'master_sync_started' => [
            'title' => 'Master-Synchronisierung gestartet',
            'body' => 'Alle Verbände werden nun nacheinander im Hintergrund synchronisiert.',
        ],
        'sync_started' => [
            'title' => 'Synchronisierung gestartet',
            'body' => 'Die Zielaufgabe :command läuft im Hintergrund.',
        ],
        'manually_failed' => [
            'title' => 'Status auf „Fehlgeschlagen“ aktualisiert',
        ],
        'manually_completed' => [
            'title' => 'Status auf „Abgeschlossen“ aktualisiert',
        ],
        'sync_blocked_master' => [
            'title' => 'Master-Synchronisierung ist aktiv',
            'body'  => 'Warten Sie, bis die Master-Synchronisierung abgeschlossen ist, bevor Sie eine einzelne Synchronisierung auslösen.',
        ],
        'sync_already_running' => [
            'title' => 'Synchronisierung läuft bereits',
        ],
        'sync_blocked_any' => [
            'title' => 'Synchronisierungen laufen bereits',
            'body'  => 'Warten Sie, bis alle aktiven Synchronisierungen abgeschlossen sind, bevor Sie eine Master-Synchronisierung starten.',
        ],
        'no_emails_found' => [
            'title' => 'Keine E-Mail-Adressen gefunden',
            'body' => 'Die ausgewählten Interessenten haben keine Standorte mit hinterlegten E-Mail-Adressen.',
        ],
        'error_mailer' => 'Fehler beim Senden der Kampagne über den Mailer-Dienst.',
    ],

    'sync_history' => [
        'model_label' => 'Synchronisierung',
        'plural_model_label' => 'Synchronisierungsverwaltung',
        'logs' => [
            'master_requested' => 'Master-Synchronisierung über das Panel angefordert.',
            'manually_failed' => 'Die Synchronisierung wurde von einem Administrator manuell als FEHLGESCHLAGEN markiert.',
            'manually_completed' => 'Die Synchronisierung wurde von einem Administrator manuell als ABGESCHLOSSEN markiert.',
        ],
    ],

    'mail_log' => [
        'model_label' => 'Empfängerprotokoll',
        'plural_model_label' => 'Empfänger',
    ],

    'mail_campaign' => [
        'model_label' => 'Mailing-Kampagne',
        'plural_model_label' => 'Mailverlauf',
    ],

    'defaults' => [
        'region' => 'Flandern',
    ],

    'unsubscribe' => [
        'link' => 'Abmelden',
        'text' => 'Möchten Sie diese E-Mails nicht mehr erhalten?',
        'success_title' => 'Abgemeldet',
        'success_body' => 'Sie wurden erfolgreich von unserer Mailingliste abgemeldet.',
        'confirmation_button' => 'Abmeldung bestätigen',
    ],
];
