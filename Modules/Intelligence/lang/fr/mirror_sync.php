<?php

return [
    'navigation_label' => 'Statut de la synchronisation miroir',
    'title' => 'Statut de la synchronisation miroir',
    'description' => 'Le miroir conserve une copie locale des données CAFCA ERP utilisées dans le panneau (Safety, Performance, FieldOps). Il s\'actualise automatiquement chaque jour à 04:00 et peut aussi être actualisé manuellement ici.',
    'labels' => [
        'last_sync' => 'Dernière synchronisation réussie',
        'never_synced' => 'Jamais synchronisé',
        'triggered_by_scheduled' => 'Automatique (planning quotidien)',
        'triggered_by_manual' => 'Manuelle, par :name',
        'duration' => 'Durée',
        'recent_runs' => 'Exécutions récentes',
        'error' => 'Erreur',
    ],
    'status' => [
        'running' => 'En cours',
        'completed' => 'Terminée',
        'failed' => 'Échouée',
    ],
    'actions' => [
        'refresh_now' => 'Actualiser maintenant',
        'sync_employees' => 'Synchroniser les employés',
        'sync_clients' => 'Synchroniser les clients',
        'sync_complexes' => 'Synchroniser les complexes',
        'sync_all_manual' => 'Tout synchroniser (employés + clients + complexes)',
    ],
    'tasks' => [
        'employees' => 'Employés',
        'clients' => 'Clients',
        'complexes' => 'Complexes',
        'all' => 'Employés, clients et complexes',
    ],
    'notifications' => [
        'queued_title' => 'Synchronisation en file d\'attente',
        'queued_body' => 'L\'actualisation s\'exécute en arrière-plan — cette page se met à jour automatiquement.',
        'already_running_title' => 'Synchronisation déjà en cours',
        'already_running_body' => 'Une synchronisation est déjà en cours. Attendez qu\'elle se termine avant d\'en lancer une autre.',
        'failed_title' => 'Impossible de mettre la synchronisation en file d\'attente',
        'manual_sync_done_title' => 'Synchronisation terminée',
        'manual_sync_done_body' => ':task : synchronisation réussie.',
        'manual_sync_failed_title' => 'Échec de la synchronisation',
    ],
];
