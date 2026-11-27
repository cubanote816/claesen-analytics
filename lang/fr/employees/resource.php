<?php

return [
    'label' => 'Employé',
    'plural_label' => 'Employés',
    'navigation_label' => 'Employés',
    'navigation_group' => 'Personnel',
    'placeholders' => [
        'no_function' => 'Aucune fonction renseignée',
        'total_hours' => '--- h',
        'efficiency' => '--%',
        'projects_count' => '--',
        'ai_insights_loading' => 'Analyse IA en cours...',
        'project_timeline_loading' => 'Chargement des projets récents...',
        'last_6_months' => '6 derniers mois',
        'last_month' => 'Mois dernier',
        'this_month' => 'Ce mois-ci',
        'last_quarter' => 'Dernier trimestre',
        'last_semester' => 'Dernier semestre',
        'previous_year' => 'Année précédente',
        'from' => 'Du',
        'to' => 'Au',
    ],

    'navigation' => [
        'details' => 'Détails',
        'edit' => 'Modifier',
        'performance' => 'Performance',
    ],

    'fields' => [
        'id' => 'ID',
        'name' => 'Nom',
        'email' => 'E-mail',
        'mobile' => 'GSM',
        'phone' => 'Téléphone',
        'is_active' => 'Statut',
        'function_default' => 'Général',
        'job_function' => 'Fonction',
        'avatar' => 'Avatar',
        'address' => 'Adresse',
        'street' => 'Rue',
        'zip' => 'Code postal',
        'city' => 'Ville',
        'country' => 'Pays',
        'notes' => 'Notes',
        'personal_information' => 'Informations personnelles',
        'contact_details' => 'Coordonnées',
    ],

    'status' => [
        'all' => 'Tous',
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'actions' => [
        'performance' => 'Performance',
        'edit' => [
            'label' => 'Modifier',
        ],
        'sync' => [
            'label' => 'Synchroniser SAP/ERP',
            'notification' => [
                'success' => 'Synchronisation terminée : :created nouveaux, :updated mis à jour.',
                'error' => 'Une erreur s\'est produite lors de la synchronisation.',
            ],
            'command' => [
                'description' => 'Synchroniser les techniciens depuis Legacy SQL Server vers MySQL local',
                'starting' => 'Démarrage de la synchronisation des techniciens...',
                'up_to_date' => 'Tous les enregistrements sont déjà à jour.',
                'success' => 'Synchronisation terminée avec succès.',
                'failed' => 'Échec de la synchronisation : :error',
                'table' => [
                    'created' => 'Créés',
                    'updated' => 'Mis à jour',
                    'errors' => 'Erreurs',
                ],
            ],
        ],
        'analyze' => [
            'label' => 'Lancer l\'analyse IA',
            'confirm' => [
                'title' => 'Lancer une nouvelle analyse ?',
                'body' => 'Les données de performance des 6 derniers mois seront récupérées à nouveau et analysées par l\'IA. Cela peut prendre un moment.',
            ],
            'notification' => [
                'success' => 'Analyse lancée en arrière-plan. Actualisez la page dans quelques instants.',
            ],
        ],
    ],
    'sections' => [
        'business_card' => 'Carte de visite',
        'performance_dashboard' => 'Tableau de bord des performances',
        'performance_dashboard_desc' => 'Analyse de la répartition des heures et de l\'activité des projets sur la période sélectionnée.',
        'ai_insights' => 'Aperçu IA & archétype',
        'project_timeline' => 'Chronologie des projets',
        'project_timeline_title' => 'CHRONOLOGIE DES PROJETS',
        'hours_distribution' => 'RÉPARTITION DES HEURES',
        'watchdog_alerts' => 'ALERTE WATCHDOG',
        'watchdog_description' => 'Alertes critiques pour ce profil.',
    ],

    'insights' => [
        'archetype' => 'Archétype du technicien',
        'burnout_risk' => 'Risque de burn-out',
        'efficiency_trend' => 'Tendance d\'efficacité',
        'current_project' => 'Projets actifs (30 j)',
        'manager_insight' => 'Avis du responsable',
        'last_audited' => 'Dernier audit',
        'status' => [
            'stable' => 'Stable',
            'increasing' => 'En hausse',
            'decreasing' => 'En baisse',
        ],
    ],

    'messages' => [
        'watchdog_warning' => 'Anomalie de performance critique détectée. Vérifiez immédiatement les détails du projet.',
    ],

    'stats' => [
        'total_hours' => 'Total travaillé',
        'efficiency' => 'Efficacité',
        'projects_count' => 'Projets actifs',
    ],

    'empty_state' => [
        'title' => 'Aucune donnée disponible',
        'description' => 'Aucune heure de travail enregistrée pour :name sur la période sélectionnée.',
        'action_this_month' => 'Voir le mois précédent',
        'action_previous_year' => 'Voir l\'année précédente',
        'erp_unavailable_title' => 'ERP temporairement indisponible',
        'erp_unavailable_description' => 'La connexion au système de gestion a été perdue. Les données ne peuvent pas être chargées pour le moment.',
        'erp_unavailable_retry' => 'Réessayer',
        'no_activity_title' => 'Aucune activité sur cette période',
        'no_activity_description' => 'Aucune heure de travail trouvée pour :name sur la période sélectionnée. Essayez une autre période.',
        'no_activity_suggestion' => 'Voir le dernier semestre',
    ],

    'dashboard' => [
        'active_range' => 'PLAGE ACTIVE',
        'global_load' => 'CHARGE GLOBALE',
        'project_showcase' => 'APERÇU DES PROJETS',
        'project_insight' => 'Aperçu du projet',
        'efficiency' => 'Efficacité',
        'verified_match' => 'Correspondance vérifiée',
        'view_archives' => 'Voir les archives',
        'custom_range' => 'Période personnalisée',
        'loading' => [
            'focus' => 'Focus des analyses',
            'sync' => 'Synchronisation des données...',
            'stage' => 'Étape des analyses',
        ],
    ],
];
