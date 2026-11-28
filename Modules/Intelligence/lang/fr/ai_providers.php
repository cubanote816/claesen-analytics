<?php

// CLA-714: AI providers screen.
return [
    'navigation_label' => 'Fournisseurs d\'IA',
    'title' => 'Fournisseurs d\'IA',
    'sections' => [
        'keys' => 'Clés d\'API',
        'keys_desc' => 'Les clés restent dans la configuration du serveur (.env). Cette page ne les affiche ni ne les enregistre jamais.',
    ],
    'tasks' => [
        'lead_summary' => 'Résumé de prospect (hot leads)',
    ],
    'fields' => [
        'provider' => 'Fournisseur',
        'model' => 'Modèle',
        'use_configuration' => 'Utiliser la configuration',
    ],
    'actions' => [
        'test' => 'Tester',
        'save' => 'Enregistrer',
    ],
    'notifications' => [
        'saved_title' => 'Paramètres d\'IA enregistrés',
        'save_failed_title' => 'Paramètres d\'IA non enregistrés',
        'test_ok_title' => 'Le fournisseur a répondu',
        'test_ok_body' => ':provider · :model · :ms ms',
        'test_failed_title' => 'Le test a échoué',
    ],
    'effective' => 'En cours : :provider · :model (:source)',
    'effective_invalid' => 'Le paramètre actuel n\'est pas valide : :reason',
    'sources' => [
        'screen' => 'choisi sur cet écran',
        'task_config' => 'configuration de la tâche',
        'default_config' => 'configuration par défaut',
    ],
    'keys' => [
        'configured' => 'configurée',
        'missing' => 'non configurée',
    ],
];
