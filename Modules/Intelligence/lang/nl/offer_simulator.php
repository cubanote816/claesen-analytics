<?php

return [
    'navigation_label' => 'Offerte Simulator',
    'title'            => 'AI Offerte Simulator',
    'sections' => [
        'project_details' => 'Project Details',
    ],
    'fields' => [
        'description'             => 'Projectbeschrijving',
        'description_placeholder' => 'Beschrijf het project (bijv. 4 masten van 15m in Brugge...)',
        'category'                => 'Categorie',
        'zipcode'                 => 'Postcode',
        'complexity'              => 'Moeilijkheidsgraad (Factor)',
        'complexity_hint'         => 'AI suggereert dit op basis van de tekst, maar u kunt dit aanpassen.',
    ],
    'actions' => [
        'simulate' => 'Simuleer Offerte',
    ],
    'notifications' => [
        'off_topic_title' => 'Niet relevant',
        'off_topic_body'  => 'Gelieve alleen verzoeken met betrekking tot verlichting of elektriciteit in te voeren.',
        'gibberish_title' => 'Onzin gedetecteerd',
        'gibberish_body'  => 'De Lead Architect is niet onder de indruk. Probeer het opnieuw met een echt project.',
    ],
];
