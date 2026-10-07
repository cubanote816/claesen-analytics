<?php

return [
    'cluster_label' => 'Website',
    'v1_demo_link' => 'Bekijk Website V1 (Demo)',
    'safety_pwa_link' => 'Claesen safety',
    'projects' => [
        'label' => 'Project',
        'plural_label' => 'Projecten',
        'sections' => [
            'details' => 'Projectdetails',
            'media' => 'Media',
            'settings' => 'Instellingen',
            'work_details' => 'In Actie',
        ],
        'fields' => [
            'title' => 'Titel',
            'slug' => 'Slug',
            'category' => 'Categorie',
            'client' => 'Klant',
            'location' => 'Locatie',
            'year' => 'Jaar',
            'description' => 'Omschrijving',
            'published' => 'Gepubliceerd',
            'featured' => 'Uitgelicht',
            'order_index' => 'Volgorde',
            'order_index_helper' => 'Bepaalt de positie op de website (1 = eerste, 0 = standaard of onderaan).',
            'featured_image' => 'Uitgelichte afbeelding',
            'gallery' => 'Galerij',
            'detail_gallery' => 'Detailgalerij',
            'work_story' => 'Projectverhaal',
            'challenge' => 'Uitdaging',
            'solution' => 'Oplossing',
            'result' => 'Resultaat',
        ],
    ],
    'pages' => [
        'label' => 'Pagina',
        'plural_label' => 'Pagina\'s',
        'sections' => [
            'content' => 'Inhoud',
            'seo' => 'SEO',
            'settings' => 'Instellingen',
        ],
        'fields' => [
            'title' => 'Titel',
            'slug' => 'Slug',
            'content' => 'Inhoud',
            'meta_description' => 'Meta Omschrijving',
            'meta_keywords' => 'Meta Trefwoorden',
            'published' => 'Gepubliceerd',
            'published_at' => 'Gepubliceerd op',
            'order_index' => 'Volgorde',
        ],
    ],
    'consultation_requests' => [
        'label' => 'Adviesaanvraag',
        'plural_label' => 'Adviesaanvragen',
        'sections' => [
            'contact' => 'Contactinformatie',
            'details' => 'Aanvraagdetails',
            'management' => 'Intern Beheer',
            'message' => 'Bericht',
            'status' => 'Status',
        ],
        'fields' => [
            'name' => 'Naam',
            'email' => 'E-mail',
            'phone' => 'Telefoon',
            'company' => 'Bedrijf',
            'type' => 'Type',
            'project_type' => 'Projecttype',
            'message' => 'Bericht',
            'status' => 'Status',
            'priority' => 'Prioriteit',
            'assigned_to' => 'Toegewezen aan',
            'follow_up_date' => 'Opvolgdatum',
            'internal_notes' => 'Interne opmerkingen',
            'tags' => 'Labels',
            'first_response_sla' => 'Eerste reactie',
        ],
        'categories' => [
            'sport' => 'Sport',
            'industrial' => 'Industrieel',
            'public' => 'Openbaar',
        ],
        'types' => [
            'consultation' => 'Advies',
            'quote' => 'Offerte',
            'project' => 'Project',
        ],
        'project_types' => [
            'sport' => 'Sport',
            'industrial' => 'Industrieel',
            'public' => 'Openbaar',
            'masts' => 'Masten',
            'other' => 'Overig',
        ],
        'status_options' => [
            'new' => 'Nieuw',
            'assigned' => 'Toegewezen',
            'in_progress' => 'In behandeling',
            'waiting_client' => 'Wacht op klant',
            'closed' => 'Gesloten',
            'spam' => 'Spam',
        ],
        'priority_options' => [
            'low' => 'Laag',
            'medium' => 'Gemiddeld',
            'high' => 'Hoog',
            'urgent' => 'Urgent',
        ],
        'actions' => [
            'export' => 'CSV exporteren',
            'erase' => 'Wissen (AVG)',
        ],
    ],
    'activities' => [
        'label' => 'Activiteit',
        'plural_label' => 'Activiteiten',
        'sections' => [
            'general' => 'Algemeen',
        ],
        'fields' => [
            'title' => 'Titel',
            'type' => 'Type',
            'user' => 'Gebruiker',
            'date' => 'Datum',
            'description' => 'Omschrijving',
            'old_value' => 'Oude waarde',
            'new_value' => 'Nieuwe waarde',
        ],
        'types' => [
            'status_change' => 'Statuswijziging',
            'priority_change' => 'Prioriteitswijziging',
            'assignment_change' => 'Toewijzing',
            'follow_up_update' => 'Opvolging bijgewerkt',
            'comment' => 'Opmerking',
            'created' => 'Aangemaakt',
            'reminder_triggered' => 'Herinnering geactiveerd',
        ],
        'logs' => [
            'created' => 'Nieuwe adviesaanvraag ontvangen via :source',
            'status_change' => 'Status gewijzigd van :old naar :new',
            'priority_change' => 'Prioriteit bijgewerkt naar :priority',
            'assignment_change' => 'Toegewezen aan :user',
            'follow_up_update' => 'Opvolgdatum ingesteld op :date',
            'comment' => 'Interne opmerkingen bijgewerkt',
            'reminder_triggered' => 'Herinnering geactiveerd: :title',
        ],
        'notifications' => [
            'new_request_title' => 'Nieuwe Adviesaanvraag',
            'new_request_body' => ':name heeft een nieuwe aanvraag ingediend.',
            'reminder_due_title' => 'Herinnering vervallen',
            'reminder_due_body' => ':title — :name',
        ],
    ],
    // WU4: quién publicó, cuándo y qué cambió. El «qué cambió» sale del log de actividad que
    // ya existía, acotado a lo posterior a la última publicación aceptada — no de un hash
    // nuevo. Ver odd/tasks/publish-to-live-website.md.
    'publication_history' => [
        'label' => 'Publicaties',
        'changes_heading' => 'Wijzigingen sinds de laatste publicatie',
        'empty' => 'Nog geen publicaties',
        'empty_changes' => 'Geen wijzigingen sinds de laatste publicatie',
        'fields' => [
            'when'           => 'Wanneer',
            'who'            => 'Wie',
            'event'          => 'Wat',
            'reason'         => 'Reden',
            'error'          => 'Fout',
            'subject'        => 'Onderdeel',
            'fields_changed' => 'Gewijzigde velden',
        ],
        'events' => [
            'requested' => 'Aangevraagd',
            'accepted'  => 'Geaccepteerd',
            'failed'    => 'Mislukt',
        ],
        // En el idioma de quien mira, no el nombre de la clase.
        'subjects' => [
            'sitesetting'  => 'Instelling',
            'project'      => 'Project',
            'mediaslot'    => 'Mediaslot',
            'announcement' => 'Mededeling',
        ],
    ],
    'publication' => [
        'status' => [
            'idle'     => 'Geen wijzigingen',
            'pending'  => 'Wijzigingen niet gepubliceerd',
            'accepted' => 'Publicatie aangevraagd',
            'error'    => 'Publicatiefout',
        ],
        'widget' => [
            'status_label'  => 'Publicatiestatus',
            'last_accepted' => 'Laatste aanvraag',
            'build_status'  => 'Bouwstatus frontend',
            'building'      => 'Aan het bouwen...',
            'unreachable'   => 'Niet bereikbaar',
            'no_data'       => '—',
            'release'       => 'Release: :release',
        ],
        'actions' => [
            'publish_now'                => 'Nu publiceren',
            'publish_now_confirm_title'  => 'Publicatie starten',
            'publish_now_confirm_body'   => 'Dit vraagt een volledige herbouw van de website aan. De huidige versie blijft actief tot de bouw klaar is.',
            'publish_now_success'        => 'Publicatie aangevraagd',
        ],
    ],
    // CLA-481 (media slots): estas claves no existían en ningún fichero de idioma, así que
    // la pantalla del panel enseñaba `website.media_slots.plural_label` en crudo, también
    // en la barra de pestañas del clúster. El texto sigue el vocabulario del propio código
    // (un slot es un nombre estable —`home.hero`— que apunta a un media de un proyecto),
    // no una interpretación libre; si el equipo de esa pantalla prefiere otra redacción,
    // esta es la que hay que cambiar.
    'media_slots' => [
        'label' => 'Mediaslot',
        'plural_label' => 'Mediaslots',
        'fields' => [
            'slot' => 'Slot',
            'slot_helper' => 'Naam die de website gebruikt, bijvoorbeeld home.hero. De website moet mee als je die wijzigt.',
            'media' => 'Media van een project',
            'media_invalid' => 'Deze media hoort niet bij een project van deze site.',
        ],
    ],

    'page_publication_review' => [
        'label' => 'Pagina-publicaties',
        'fields' => [
            'page' => 'Pagina',
            'locale' => 'Taal',
            'status' => 'Status',
            'reviewed_at' => 'Nagekeken',
            'reviewed_by' => 'Nagekeken door',
        ],
        // El vocabulario interno, dicho como lo dice quien aprueba: «Nagekeken» ya es
        // la etiqueta de `reviewed_at` en esta misma pantalla, así que el estado usa
        // la misma palabra en vez de inventar otra.
        'statuses' => [
            'machine' => 'Concept',
            'reviewed' => 'Nagekeken',
            'published' => 'Gepubliceerd',
        ],
        // Los códigos ISO no le dicen nada a quien aprueba una traducción.
        'locales' => [
            'nl' => 'Nederlands',
            'fr' => 'Frans',
            'en' => 'Engels',
            'de' => 'Duits',
        ],
        // Copiados del menú del propio sitio (electrobertels-official,
        // src/content/chrome/nl.yaml): son los nombres que el cliente ya aprobó para
        // esas páginas, no una invención de esta pantalla. Si allí cambian, hay que
        // volver a copiarlos: el backend no lee ese repositorio.
        'pages' => [
            'home' => 'Home',
            'particulieren' => 'Particulieren',
            'bedrijven' => 'Bedrijven & industrie',
            'winkel' => 'Winkel',
            'projecten' => 'Projecten',
            'over-ons' => 'Over ons',
            'contact' => 'Contact & prijsaanvraag',
        // Los títulos salen del contenido del sitio (es su copy), no de acá.
        // Los legales sólo existen en nl allá, por eso no hay versión en inglés de estos.
            'legal-privacy' => 'Privacyverklaring',
            'legal-cookies' => 'Cookiebeleid',
            'legal-terms' => 'Algemene voorwaarden',
            'legal-privacy' => 'Privacyverklaring',
            'legal-cookies' => 'Cookiebeleid',
            'legal-terms' => 'Algemene voorwaarden',
        ],

        'actions' => [
            'approve' => 'Goedkeuren',
            'publish' => 'Publiceren',
            'retire' => 'Terugtrekken',
            'publish_confirm' => 'Deze pagina wordt indexeerbaar in deze taal. Doorgaan?',
            'retire_confirm' => 'Deze pagina wordt niet langer indexeerbaar in deze taal. Doorgaan?',
        ],
        'notifications' => [
            'updated' => 'Publicatiestatus bijgewerkt',
        ],
    ],

    'translation_review' => [
        'label' => 'Vertaalreview',
        'fields' => [
            'subject' => 'Onderwerp',
            'attribute' => 'Veld',
            'locale' => 'Taal',
            'status' => 'Status',
            'error' => 'Fout',
            'updated_at' => 'Bijgewerkt',
        ],
        // Los siete estados del ciclo de vida (documentados en TranslationState), dichos
        // como los diría quien aprueba. «Nagekeken» es la misma palabra que ya usa la
        // columna, y es también la que usa la pantalla de publicaciones de páginas.
        'statuses' => [
            'missing' => 'Ontbreekt',
            'machine' => 'Concept',
            'stale' => 'Verouderd',
            'needs_review' => 'Na te kijken',
            'reviewed' => 'Nagekeken',
            'published' => 'Gepubliceerd',
            'failed' => 'Mislukt',
        ],

        'actions' => [
            'edit' => 'Bewerken',
            'edit_value' => 'Vertaling (:locale)',
            'approve' => 'Goedkeuren',
            'publish' => 'Publiceren',
            'retranslate' => 'Opnieuw vertalen (AI)',
            'retranslate_confirm' => 'De goedgekeurde waarde wordt overschreven door een nieuwe AI-vertaling en moet opnieuw worden goedgekeurd. Doorgaan?',
        ],
        'notifications' => [
            'updated' => 'Vertaalstatus bijgewerkt',
        ],
    ],

    'announcements' => [
        'label' => 'Mededeling',
        'plural_label' => 'Mededelingen',
        'message_helper' => 'Wordt getoond in de huidige taal van deze beheerdersweergave.',
        'starts_at_helper' => 'Leeg = direct actief zodra gepubliceerd.',
        'ends_at_helper' => 'Leeg = blijft actief tot handmatig gearchiveerd.',
        'status' => [
            'draft' => 'Concept',
            'published' => 'Gepubliceerd',
            'archived' => 'Gearchiveerd',
        ],
    ],
    // WU-S2: la división de Ajustes en seis páginas, tal como la declara el prototipo
    // aprobado. `settings_sections.*.label` es el nombre en el menú y el título de la página, y
    // `.description` es la línea que va debajo del título.
    'settings_sections' => [
        'general' => [
            'label' => 'Algemeen',
            'description' => 'Juridische identiteit voor de footer, de juridische pagina\'s en de zoekdata.',
        ],
        'contact' => [
            'label' => 'Contact',
            'description' => 'Telefoon, WhatsApp en e-mail zoals ze op de site verschijnen.',
        ],
        'address' => [
            'label' => 'Adres en kaarten',
            'description' => 'Waar de zaak is en hoe bezoekers die vinden.',
        ],
        'hours' => [
            'label' => 'Openingsuren',
            'description' => 'Weekrooster in de footer en op de contactpagina.',
        ],
        'social' => [
            'label' => 'Sociale links',
            'description' => 'Profielen waarnaar de footer linkt.',
        ],
        'privacy' => [
            'label' => 'Privacy en toestemming',
            'description' => 'Embeds die van cookies afhangen en het bijhouden van toestemming.',
        ],
    ],
    'site_settings' => [

        // Het adres wordt per deel bewerkt, niet als JSON: het is een vaste vorm en een
        // typefout binnen een JSON-blob blijft geldige JSON, dus ze glipt erdoor.
        'address' => [
            'street' => 'Straat en nummer',
            'postal_code' => 'Postcode',
            'city' => 'Gemeente',
            'country_code' => 'Landcode (2 letters)',
        ],
        // De openingsuren worden per dag bewerkt, niet als JSON: `hours: null` IS
        // "gesloten" in het contract, dus de schakelaar wordt daaruit afgeleid.
        'opening_hours' => [
            'day' => 'Dag',
            'closed' => 'Gesloten',
            'ranges' => 'Openingsuren',
            'opens' => 'Van',
            'closes' => 'Tot',
            'add_day' => 'Dag toevoegen',
            'add_range' => 'Periode toevoegen',
            // De sleutels zijn contractdata; alleen het label wordt vertaald.
            'days' => [
                'sunday' => 'Zondag',
                'monday' => 'Maandag',
                'tuesdayToFriday' => 'Dinsdag t/m vrijdag',
                'saturday' => 'Zaterdag',
            ],
        ],
        'label' => 'Site-instellingen',
        'save' => 'Opslaan',
        'saved' => 'Instellingen opgeslagen',
        'fields' => [
            'hours' => 'Openingstijden',
            'phone' => 'Telefoon',
            'email' => 'E-mail',
            'address' => 'Adres',
            'social_links' => 'Social media links (JSON)',
            'legal_name' => 'Wettelijke naam',
            'founded_year' => 'Oprichtingsjaar',
            'phone_display' => 'Telefoon (weergave)',
            'phone_tel' => 'Telefoon (tel: link)',
            'whatsapp_display' => 'WhatsApp (weergave)',
            'whatsapp_url' => 'WhatsApp (URL)',
            'address_structured' => 'Adres (gestructureerd)',
            'address_country' => 'Land (vertaalbaar)',
            'maps_embed_url' => 'Maps insluiten (URL)',
            'maps_directions_url' => 'Maps route (URL)',
            'maps_consent_mode' => 'Maps toestemming',
            'vat_number' => 'BTW-nummer',
            'opening_hours' => 'Openingstijden (gestructureerd)',
            'contact_consent_version' => 'Toestemmingsversie contactformulier',
        ],
        'hints' => [
            'address' => 'Claesen: vrije tekenreeks, ongewijzigd door het Claesen-frontend gelezen. Niet bewerken voor Electro Bertels.',
            'address_structured' => 'Electro Bertels: gestructureerde bron — JSON met street, postal_code, city, country_code. Het Claesen-frontend leest dit veld niet.',
            'founded_year' => 'JSON-geheel getal, bijv. 1977',
            'opening_hours' => 'Eén rij per dag. "Gesloten" betekent: geen openingsuren.',
            'maps_consent_mode' => 'always | on_consent | never',
        ],
    ],
];
