<?php

return [
    'cluster_label' => 'Website',
    'v1_demo_link' => 'Website V1 ansehen (Demo)',
    'safety_pwa_link' => 'Claesen Sicherheit',
    'projects' => [
        'label' => 'Projekt',
        'plural_label' => 'Projekte',
        'sections' => [
            'details' => 'Projektdetails',
            'media' => 'Medien',
            'settings' => 'Einstellungen',
        ],
        'fields' => [
            'title' => 'Titel',
            'slug' => 'Slug',
            'category' => 'Kategorie',
            'client' => 'Kunde',
            'location' => 'Standort',
            'year' => 'Jahr',
            'description' => 'Beschreibung',
            'published' => 'Veröffentlicht',
            'featured' => 'Hervorgehoben',
            'order_index' => 'Reihenfolge',
            'order_index_helper' => 'Bestimmt die Position auf der Website (1 = erstes, 0 = Standard oder unten).',
            'featured_image' => 'Hauptbild',
            'gallery' => 'Galerie',
        ],
    ],
    'pages' => [
        'label' => 'Seite',
        'plural_label' => 'Seiten',
        'sections' => [
            'content' => 'Inhalt',
            'seo' => 'SEO',
            'settings' => 'Einstellungen',
        ],
        'fields' => [
            'title' => 'Titel',
            'slug' => 'Slug',
            'content' => 'Inhalt',
            'meta_description' => 'Meta-Beschreibung',
            'meta_keywords' => 'Meta-Schlüsselwörter',
            'published' => 'Veröffentlicht',
            'published_at' => 'Veröffentlicht am',
            'order_index' => 'Reihenfolge',
        ],
    ],
    'consultation_requests' => [
        'label' => 'Beratungsanfrage',
        'plural_label' => 'Beratungsanfragen',
        'sections' => [
            'contact' => 'Kontaktinformationen',
            'details' => 'Anfragedetails',
            'management' => 'Interne Verwaltung',
            'message' => 'Nachricht',
            'status' => 'Status',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'company' => 'Unternehmen',
            'type' => 'Typ',
            'project_type' => 'Projekttyp',
            'message' => 'Nachricht',
            'status' => 'Status',
            'priority' => 'Priorität',
            'assigned_to' => 'Zugewiesen an',
            'follow_up_date' => 'Folgetermin',
            'internal_notes' => 'Interne Notizen',
            'tags' => 'Tags',
            'first_response_sla' => 'Erste Antwort',
        ],
        'categories' => [
            'sport' => 'Sport',
            'industrial' => 'Industrie',
            'public' => 'Öffentlich',
        ],
        'types' => [
            'consultation' => 'Beratung',
            'quote' => 'Angebot',
            'project' => 'Projekt',
        ],
        'project_types' => [
            'sport' => 'Sport',
            'industrial' => 'Industrie',
            'public' => 'Öffentlich',
            'masts' => 'Masten',
            'other' => 'Sonstige',
        ],
        'status_options' => [
            'new' => 'Neu',
            'assigned' => 'Zugewiesen',
            'in_progress' => 'In Bearbeitung',
            'waiting_client' => 'Wartet auf Kunde',
            'closed' => 'Geschlossen',
            'spam' => 'Spam',
        ],
        'priority_options' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'urgent' => 'Dringend',
        ],
        'actions' => [
            'export' => 'CSV exportieren',
            'erase' => 'Löschen (DSGVO)',
        ],
    ],
    'activities' => [
        'label' => 'Aktivität',
        'plural_label' => 'Aktivitäten',
        'sections' => [
            'general' => 'Allgemein',
        ],
        'fields' => [
            'title' => 'Titel',
            'type' => 'Typ',
            'user' => 'Benutzer',
            'date' => 'Datum',
            'description' => 'Beschreibung',
            'old_value' => 'Alter Wert',
            'new_value' => 'Neuer Wert',
        ],
        'types' => [
            'status_change' => 'Statusänderung',
            'priority_change' => 'Prioritätsänderung',
            'assignment_change' => 'Zuweisung',
            'follow_up_update' => 'Folgetermin aktualisiert',
            'comment' => 'Kommentar',
            'created' => 'Erstellt',
            'reminder_triggered' => 'Erinnerung ausgelöst',
        ],
        'logs' => [
            'created' => 'Neue Beratungsanfrage über :source eingegangen',
            'status_change' => 'Status geändert von :old zu :new',
            'priority_change' => 'Priorität aktualisiert auf :priority',
            'assignment_change' => 'Zugewiesen an :user',
            'follow_up_update' => 'Folgetermin festgelegt auf :date',
            'comment' => 'Interne Notizen aktualisiert',
            'reminder_triggered' => 'Erinnerung ausgelöst: :title',
        ],
        'notifications' => [
            'new_request_title' => 'Neue Beratungsanfrage',
            'new_request_body' => ':name hat eine neue Anfrage gestellt.',
            'reminder_due_title' => 'Erinnerung fällig',
            'reminder_due_body' => ':title — :name',
        ],
    ],
    'translation_review' => [
        'label' => 'Übersetzungsprüfung',
        'fields' => [
            'subject' => 'Objekt',
            'attribute' => 'Feld',
            'locale' => 'Sprache',
            'status' => 'Status',
            'error' => 'Fehler',
            'updated_at' => 'Aktualisiert',
        ],
        'actions' => [
            'edit' => 'Bearbeiten',
            'edit_value' => 'Übersetzung (:locale)',
            'approve' => 'Genehmigen',
            'publish' => 'Veröffentlichen',
            'retranslate' => 'Neu übersetzen (KI)',
            'retranslate_confirm' => 'Der genehmigte Wert wird durch eine neue KI-Übersetzung überschrieben und muss erneut genehmigt werden. Fortfahren?',
        ],
        'notifications' => [
            'updated' => 'Übersetzungsstatus aktualisiert',
        ],
    ],

    'announcements' => [
        'label' => 'Ankündigung',
        'plural_label' => 'Ankündigungen',
        'message_helper' => 'Wird in der aktuellen Sprache dieser Admin-Ansicht angezeigt.',
        'starts_at_helper' => 'Leer = sofort aktiv nach Veröffentlichung.',
        'ends_at_helper' => 'Leer = bleibt aktiv bis manuell archiviert.',
        'status' => [
            'draft' => 'Entwurf',
            'published' => 'Veröffentlicht',
            'archived' => 'Archiviert',
        ],
    ],
    // WU4: wer veröffentlicht hat, wann, und was sich geändert hat. Das «was» kommt aus dem
    // bereits vorhandenen Aktivitätsprotokoll, begrenzt auf alles nach der letzten
    // angenommenen Veröffentlichung.
    'publication_history' => [
        'label' => 'Veröffentlichungen',
        'changes_heading' => 'Änderungen seit der letzten Veröffentlichung',
        'empty' => 'Noch keine Veröffentlichungen',
        'empty_changes' => 'Keine Änderungen seit der letzten Veröffentlichung',
        'fields' => [
            'when'           => 'Wann',
            'who'            => 'Wer',
            'event'          => 'Was',
            'reason'         => 'Grund',
            'error'          => 'Fehler',
            'subject'        => 'Bereich',
            'fields_changed' => 'Geänderte Felder',
        ],
        'events' => [
            'requested' => 'Angefordert',
            'accepted'  => 'Angenommen',
            'failed'    => 'Fehlgeschlagen',
        ],
        'subjects' => [
            'sitesetting'  => 'Einstellung',
            'project'      => 'Projekt',
            'mediaslot'    => 'Medienslot',
            'announcement' => 'Mitteilung',
        ],
    ],
    'publication' => [
        'status' => [
            'idle'     => 'Keine Änderungen',
            // « In der Warteschlange » (der alte Text) beschrieb das automatische Modell: seit
            // der bewussten Veröffentlichung (2026-10-06) heißt der Zustand, dass Änderungen
            // auf eine menschliche Entscheidung warten — nicht, dass ein Build eingereiht ist.
            'pending'  => 'Änderungen nicht veröffentlicht',
            'accepted' => 'Veröffentlichung angefordert',
            'error'    => 'Veröffentlichungsfehler',
        ],
        'widget' => [
            'status_label'  => 'Veröffentlichungsstatus',
            'last_accepted' => 'Letzte Anfrage',
            'build_status'  => 'Build-Status des Frontends',
            'building'      => 'Wird erstellt...',
            'unreachable'   => 'Nicht erreichbar',
            'no_data'       => '—',
            'release'       => 'Release: :release',
        ],
        'actions' => [
            'publish_now'                => 'Jetzt veröffentlichen',
            'publish_now_confirm_title'  => 'Veröffentlichung starten',
            'publish_now_confirm_body'   => 'Dies fordert einen vollständigen Neuaufbau der Website an. Die aktuelle Version bleibt online, bis der Build abgeschlossen ist.',
            'publish_now_success'        => 'Veröffentlichung angefordert',
            'publish_section'                 => 'Diese Seite veröffentlichen',
            'publish_section_confirm_title'   => ':page veröffentlichen',
            'publish_section_confirm_body'    => 'Nur die Änderungen von :page werden an die Website gesendet. Änderungen auf den anderen Seiten behalten vorerst ihre veröffentlichten Werte.',
            'publish_section_confirm_pending' => 'Änderungen auf dieser Seite, die auf die Veröffentlichung warten: :count',
            'publish_section_confirm_submit'  => ':page veröffentlichen',
            'publish_section_success'         => 'Die Veröffentlichung von :page wurde angefordert',
            'publish_section_disabled'        => 'Die Veröffentlichung ist in dieser Umgebung deaktiviert — die Website wurde nicht aktualisiert.',
            'publish_section_no_drift'        => 'Nichts zu veröffentlichen — diese Seite entspricht bereits der veröffentlichten Website.',
            'publish_section_unverified'      => 'Es konnte nicht geprüft werden, ob etwas zu veröffentlichen war — die Veröffentlichung wurde trotzdem angefordert.',
        ],
    ],
    // WU-S2: die Aufteilung der Website-Einstellungen in sechs Seiten, wie der genehmigte
    // Prototyp sie deklariert.
    // WU-S1: die vier Gruppen des Website-Untermenüs, aus dem Prototyp. Der Wert muss zwischen
    // den Einträgen einer Gruppe DERSELBE sein (Filament gruppiert nach Text), daher wird er an
    // einer Stelle aufgelöst.
    // WU-S3: die Leiste für nicht gespeicherte Änderungen. Sie ersetzt die Schaltfläche
    // «Speichern» am Ende des Formulars.
    'settings_bar' => [
        'unsaved'    => 'Nicht gespeicherte Änderungen',
        'discard'    => 'Verwerfen',
        'save_draft' => 'Entwurf speichern',
        'discarded'  => 'Änderungen verworfen',
    ],
    'nav_groups' => [
        'publishing'    => 'Veröffentlichung',
        'content'       => 'Inhalt',
        'site_settings' => 'Website-Einstellungen',
        'inbox'         => 'Posteingang',
    ],
    'settings_sections' => [
        'general' => [
            'label' => 'Allgemein',
            'description' => 'Rechtliche Identität für Footer, Rechtsseiten und Suchdaten.',
        ],
        'contact' => [
            'label' => 'Kontakt',
            'description' => 'Telefon, WhatsApp und E-Mail, wie sie auf der Website erscheinen.',
        ],
        'address' => [
            'label' => 'Adresse und Karten',
            'description' => 'Wo der Betrieb ist und wie Besucher ihn finden.',
        ],
        'hours' => [
            'label' => 'Öffnungszeiten',
            'description' => 'Wochenplan im Footer und auf der Kontaktseite.',
        ],
        'social' => [
            'label' => 'Social-Media-Links',
            'description' => 'Profile, auf die der Footer verlinkt.',
        ],
        'privacy' => [
            'label' => 'Datenschutz und Einwilligung',
            'description' => 'Cookie-abhängige Einbettungen und Einwilligungsverfolgung.',
        ],
    ],
    'site_settings' => [

        // Die Adresse wird in Teilen bearbeitet, nicht als JSON: es ist eine feste Form, und
        // ein Tippfehler in einem JSON-Blob bleibt gültiges JSON, also rutscht er durch.
        'address' => [
            'street' => 'Straße und Nummer',
            'postal_code' => 'Postleitzahl',
            'city' => 'Gemeinde',
            'country_code' => 'Ländercode (2 Buchstaben)',
        ],
        'label' => 'Website-Einstellungen',
        'save' => 'Speichern',
        'saved' => 'Einstellungen gespeichert',
        // Öffnungszeiten werden pro Tag bearbeitet, nicht als JSON: `hours: null` IST
        // "geschlossen" im Vertrag, daher wird der Schalter daraus abgeleitet.
        'opening_hours' => [
            'day' => 'Tag',
            'closed' => 'Geschlossen',
            'ranges' => 'Öffnungszeiten',
            'opens' => 'Von',
            'closes' => 'Bis',
            'add_day' => 'Tag hinzufügen',
            'add_range' => 'Zeitraum hinzufügen',
            // Die Schlüssel sind Vertragsdaten; nur das Label wird übersetzt.
            'days' => [
                'sunday' => 'Sonntag',
                'monday' => 'Montag',
                'tuesdayToFriday' => 'Dienstag bis Freitag',
                'saturday' => 'Samstag',
            ],
        ],
        'fields' => [
            'hours' => 'Öffnungszeiten',
            'phone' => 'Telefon',
            'email' => 'E-Mail',
            'address' => 'Adresse',
            'social_links' => 'Social-Media-Links (JSON)',
            'legal_name' => 'Firmenname (rechtlich)',
            'founded_year' => 'Gründungsjahr',
            'phone_display' => 'Telefon (Anzeige)',
            'phone_tel' => 'Telefon (tel:-Link)',
            'whatsapp_display' => 'WhatsApp (Anzeige)',
            'whatsapp_url' => 'WhatsApp (URL)',
            'address_structured' => 'Adresse (strukturiert)',
            'address_country' => 'Land (übersetzbar)',
            'maps_embed_url' => 'Maps Einbettung (URL)',
            'maps_directions_url' => 'Maps Route (URL)',
            'maps_consent_mode' => 'Maps Zustimmung',
            'vat_number' => 'USt-Nummer',
            'opening_hours' => 'Öffnungszeiten (strukturiert)',
            'contact_consent_version' => 'Consent-Version (Kontaktformular)',
        ],
        'hints' => [
            'address' => 'Claesen: freier Text, wird unverändert vom Claesen-Frontend gelesen. Nicht für Electro Bertels bearbeiten.',
            'address_structured' => 'Electro Bertels: strukturierte Quelle — JSON mit street, postal_code, city, country_code. Das Claesen-Frontend liest dieses Feld nicht.',
            'founded_year' => 'Ganzzahliger JSON-Wert, z. B. 1977',
            'opening_hours' => 'Eine Zeile pro Tag. "Geschlossen" bedeutet: keine Öffnungszeiten.',
            'maps_consent_mode' => 'always | on_consent | never',
        ],
    ],
];
