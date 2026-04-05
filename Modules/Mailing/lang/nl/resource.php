<?php

return [
    'model_label' => 'E-mail Sjabloon',
    'plural_model_label' => 'E-mail Sjablonen',
    'navigation_group' => 'Groei & Acquisitie',

    'sections' => [
        'template_details' => 'Sjabloon Details',
        'content' => 'Inhoud',
        'content_desc' => 'Beheer het e-mailonderwerp en de inhoud van het bericht.',
    ],

    'fields' => [
        'name' => 'Naam van Sjabloon',
        'subject' => 'Onderwerp (E-mail Subject)',
        'body' => 'E-mail Bericht',
        'updated_at' => 'Laatst Gewijzigd',
    ],

    'placeholders' => [
        'content_description' => 'Je kunt variabelen gebruiken zoals {{ naam }} en {{ regio }}',
    ],
];
