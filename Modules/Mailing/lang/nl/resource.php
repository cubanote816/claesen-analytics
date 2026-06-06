<?php

return [
    'model_label' => 'E-mail Sjabloon',
    'plural_model_label' => 'E-mail Sjablonen',
    'navigation_group' => 'Groei & Acquisitie',

    'sections' => [
        'engagement'      => 'Betrokkenheid',
        'engagement_note' => 'Opentracking is indicatief. Apple Mail Privacy Protection en beveiligingsproxy\'s kunnen opens opblazen of verbergen. Klikken zijn het primaire engagementsignaal.',
        'no_events'       => 'Geen betrokkenheidsgebeurtenissen geregistreerd voor dit bericht.',
        'template_details' => 'Sjabloon Details',
        'variables'        => 'Sjabloon Variabelen',
        'variables_desc'   => 'Definieer de dynamische variabelen van dit sjabloon (bijv. {{ name }}, {{ regio }}).',
        'content'          => 'Inhoud',
        'content_desc'     => 'Beheer het e-mailonderwerp en de inhoud van het bericht.',
        'audience'         => 'Doelgroep',
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
        // Doelgroep
        'audience_type'            => 'Type Doelgroep',
        'segment_operator'         => 'Regelcombinatie',
        'segment_operator_and'     => 'Alle regels toepassen (EN)',
        'segment_operator_or'      => 'Minimaal één regel (OF)',
        'segment_rules'            => 'Segmentregels',
        'rule_type'                => 'Regeltype',
        'rule_has_event'           => 'Heeft actie uitgevoerd',
        'rule_has_no_event'        => 'Heeft actie NIET uitgevoerd',
        'rule_prospect_field'      => 'Contactveld komt overeen',
        'rule_event_type'          => 'Type actie',
        'rule_campaign'            => 'Van campagne (optioneel)',
        'rule_campaign_any'        => 'Elke campagne',
        'rule_within_days'         => 'Laatste N dagen (optioneel)',
        'rule_field'               => 'Veld',
        'rule_field_language'      => 'Taal',
        'rule_field_federation'    => 'Federatie',
        'rule_field_region'        => 'Regio',
        'rule_operator'            => 'Operator',
        'rule_operator_equals'     => 'Is gelijk aan (=)',
        'rule_operator_in'         => 'Is één van (in)',
        'rule_value'               => 'Waarde',
        'rule_value_helper'        => 'Voor "is één van", waarden met komma scheiden: RBFA,LBFA',
        // Betrokkenheid
        'opened'         => 'Geopend',
        'clicked'        => 'Geklikt',
        'last_event_at'  => 'Laatste activiteit',
        'event_type'     => 'Gebeurtenis',
        'link_url'       => 'Geklikt URL',
        'occurred_at'    => 'Wanneer',
        // Plannen
        'scheduled_at'        => 'Geplande verzending (optioneel)',
        'scheduled_at_helper' => 'Leeg laten om onmiddellijk na goedkeuring te verzenden. Tijdstip is Europe/Brussels.',
        'timezone'            => 'Tijdzone',
    ],

    'actions' => [
        'add_variable'     => 'Variabele toevoegen',
        'add_rule'         => 'Regel toevoegen',
        'preview_audience' => 'Doelgroep voorvertonen',
    ],

    'notifications' => [
        'audience_preview' => 'Geschatte doelgroep: :count contacten',
        'segment_error'    => 'Fout in segmentconfiguratie',
    ],

    'placeholders' => [
        'content_description' => 'Je kunt variabelen gebruiken zoals {{ naam }} en {{ regio }}',
    ],
];
