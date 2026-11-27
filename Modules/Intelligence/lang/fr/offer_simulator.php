<?php

return [
    'navigation_label' => 'Simulateur d\'offres',
    'title'            => 'Simulateur d\'offres IA',
    'sections' => [
        'project_details' => 'Détails du projet',
    ],
    'fields' => [
        'description'             => 'Description du projet',
        'description_placeholder' => 'Décrivez le projet...',
        'category'                => 'Catégorie',
        'zipcode'                 => 'Code postal',
        'complexity'              => 'Facteur de complexité',
        'complexity_hint'         => 'L\'IA le suggère, mais vous pouvez le modifier.',
    ],
    'actions' => [
        'simulate' => 'Simuler l\'offre',
    ],
    'notifications' => [
        'off_topic_title' => 'Hors sujet détecté',
        'off_topic_body'  => 'Veuillez saisir uniquement des demandes liées à l\'éclairage ou à l\'électricité.',
        'gibberish_title' => 'Non-sens détecté',
        'gibberish_body'  => 'Le Lead Architect n\'est pas convaincu. Veuillez réessayer avec un vrai projet.',
    ],
];
