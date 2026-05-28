<?php

return [
    'model_label' => 'E-mail Sjabloon',
    'plural_model_label' => 'E-mail Sjablonen',
    'navigation_group' => 'Groei & Acquisitie',

    'sections' => [
        'template_details' => 'Sjabloon Details',
        'variables'        => 'Sjabloon Variabelen',
        'variables_desc'   => 'Definieer de dynamische variabelen van dit sjabloon (bijv. {{ name }}, {{ regio }}).',
        'content'          => 'Inhoud',
        'content_desc'     => 'Beheer het e-mailonderwerp en de inhoud van het bericht.',
    ],

    'fields' => [
        'name'             => 'Naam van Sjabloon',
        'subject'          => 'Onderwerp (E-mail Subject)',
        'body'             => 'E-mail Bericht',
        'category'         => 'Categorie',
        'variables'        => 'Variabelen',
        'variable_key'     => 'Sleutel',
        'variable_label'   => 'Label',
        'variable_example' => 'Voorbeeld',
        'version'          => 'Versie',
        'updated_at'       => 'Laatst Gewijzigd',
    ],

    'actions' => [
        'add_variable' => 'Variabele toevoegen',
    ],

    'placeholders' => [
        'content_description' => 'Je kunt variabelen gebruiken zoals {{ naam }} en {{ regio }}',
    ],
];
