<?php

return [
    'navigation_label' => 'Angebotssimulator',
    'title'            => 'KI-Angebotssimulator',
    'sections' => [
        'project_details' => 'Projektdetails',
    ],
    'fields' => [
        'description'             => 'Projektbeschreibung',
        'description_placeholder' => 'Beschreiben Sie das Projekt...',
        'category'                => 'Kategorie',
        'zipcode'                 => 'Postleitzahl',
        'complexity'              => 'Komplexitätsfaktor',
        'complexity_hint'         => 'Die KI schlägt diesen Wert vor, Sie können ihn jedoch ändern.',
    ],
    'actions' => [
        'simulate' => 'Angebot simulieren',
    ],
    'notifications' => [
        'off_topic_title' => 'Themenfremde Eingabe erkannt',
        'off_topic_body'  => 'Bitte geben Sie nur Anfragen zu Beleuchtung oder Elektrik ein.',
        'gibberish_title' => 'Unsinn erkannt',
        'gibberish_body'  => 'Der Lead Architect ist nicht überzeugt. Bitte versuchen Sie es erneut mit einem echten Projekt.',
    ],
];
