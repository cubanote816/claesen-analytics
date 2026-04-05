<?php

return [
    'model_label' => 'Prospect',
    'plural_model_label' => 'Prospects',
    'navigation_group' => 'Croissance et Acquisition',

    'sections' => [
        'club_info' => 'Informations du Club',
        'marketing_targets' => 'Cibles Marketing',
        'sync_details' => 'Détails de Synchronisation',
        'logs' => 'Journaux / Événements',
        'logs_desc' => 'Vue détaillée des événements lors de l\'exécution.',
        'mailing_details' => 'Détails du Publipostage',
        'mail_details' => 'Détails du Publipostage',
        'snapshot' => 'Capture',
        'snapshot_desc' => 'Le contenu exact de l\'e-mail envoyé.',
        'campaign_summary' => 'Résumé de la Campagne',
        'recipients' => 'Destinataires',
    ],

    'fields' => [
        'name' => 'Nom du Club',
        'region' => 'Région',
        'federation' => 'Fédération',
        'language' => 'Langue',
        'contact_person' => 'Secrétaire / Personne de Contact',
        'channel' => 'Canal',
        'website' => 'Site Web',
        'vat_number' => 'Numéro de TVA',
        'cafca_id' => 'ID de Relation CAFCA',
        'locations' => 'Emplacements',
        'contact_type' => 'Type de Contact',
        'email' => 'E-mail',
        'phone' => 'Numéro de Téléphone',
        'address' => 'Adresse',
        'logo' => 'Logo',
        'has_email' => 'Possède une adresse E-mail',
        'locations_count' => 'Nombre d\'Emplacements',
        'command' => 'Commande',
        'status' => 'Statut',
        'started_by' => 'Démarré par',
        'items' => 'Articles',
        'started_at' => 'Démarré le',
        'finished_at' => 'Terminé le',
        'total_items' => 'Total des Articles traités',
        'prospect' => 'Prospect',
        'template' => 'Modèle / Campagne',
        'sent_at' => 'Envoyé le',
        'error_message' => 'Message d\'Erreur',
        'subject' => 'Objet',
        'body' => 'Contenu',
        'type_sport' => 'Type de Sport',
        'description' => 'Description / Objectif',
        'total_count' => 'Total',
        'success_count' => 'Succès',
        'failed_count' => 'Échec',
        'skipped_count' => 'Ignoré',
        'unsubscribed_at' => 'Désabonné le',
    ],

    'options' => [
        'contact_types' => [
            'headquarters' => 'Siège social',
            'stadium' => 'Stade',
            'venue_name' => 'Nom de l\'emplacement',
            'club_house' => 'Club House',
            'contact_person' => 'Personne de Contact',
            'other' => 'Autre',
        ],
        'languages' => [
            'nl' => 'Néerlandais',
            'fr' => 'Français',
            'en' => 'Anglais',
        ],
        'status' => [
            'running' => 'En cours...',
            'completed' => 'Terminé',
            'failed' => 'Échoué',
            'sent' => 'Envoyé',
            'skipped' => 'Ignoré (Pas d\'E-mail)',
            'active' => 'Actif',
            'unsubscribed' => 'Désabonné',
            'all' => 'Tous',
        ],
        'sport_types' => [
            'football_club' => 'Football',
            'athletics_club' => 'Athlétisme',
            'tennis_padel_club' => 'Tennis & Padel',
            'hockey_club' => 'Hockey',
        ],
    ],

    'actions' => [
        'execute_campaign' => [
            'label' => 'Démarrer la Campagne de Mailing',
            'form' => [
                'template' => 'Choisir le Modèle d\'E-mail',
                'description' => 'Description de la Campagne',
                'description_placeholder' => 'Ex: Suivi foire ou Introduction du nouveau catalogue...',
            ],
        ],
        'sync_master' => [
            'label' => 'Sync Tout (Master)',
        ],
        'individual_sync' => [
            'label' => 'Synchronisation Individuelle',
            'rbfa' => 'Sync RBFA (Football)',
            'lbfa' => 'Sync LBFA (Athlétisme FR)',
            'val' => 'Sync VAL (Athlétisme NL)',
            'hockey' => 'Sync Hockey (VHL/LFH)',
            'tpv' => 'Sync Tennis & Padel (TPV)',
            'aft' => 'Sync AFT (Tennis FR)',
        ],
        'apply_filters' => 'Appliquer les Filtres',
        'mark_failed' => [
            'label' => 'Marquer comme Échoué',
        ],
        'mark_completed' => [
            'label' => 'Marquer comme Terminé',
        ],
    ],

    'notifications' => [
        'no_prospects_selected' => [
            'title' => 'Aucun prospect sélectionné',
            'body' => 'Veuillez sélectionner au moins un prospect pour démarrer un mailing.',
        ],
        'campaign_started' => [
            'title' => 'Campagne Démarrée',
            'body' => 'Les e-mails sont envoyés en arrière-plan avec le modèle choisi.',
        ],
        'master_sync_started' => [
            'title' => 'Master Sync Démarrée',
            'body' => 'Toutes les fédérations sont maintenant synchronisées séquentiellement en arrière-plan.',
        ],
        'sync_started' => [
            'title' => 'Synchronisation Démarrée',
            'body' => 'La tâche :command est exécutée en arrière-plan.',
        ],
        'manually_failed' => [
            'title' => 'Statut mis à jour vers Échoué',
        ],
        'manually_completed' => [
            'title' => 'Statut mis à jour vers Terminé',
        ],
        'no_emails_found' => [
            'title' => 'Aucune adresse e-mail trouvée',
            'body' => 'Les prospects sélectionnés n\'ont pas d\'emplacements avec des adresses e-mail enregistrées.',
        ],
        'error_mailer' => 'Erreur lors de l\'envoi de la campagne via le service de messagerie.',
    ],

    'sync_history' => [
        'model_label' => 'Synchronisation',
        'plural_model_label' => 'Gestion de la Sincronisation',
        'logs' => [
            'master_requested' => 'Synchronisation Master demandée via le panneau.',
            'manually_failed' => 'La synchronisation a été marquée manuellement comme ÉCHOUÉE par un administrateur.',
            'manually_completed' => 'La synchronisation a été marquée manuellement comme TERMINÉE par un administrateur.',
        ],
    ],

    'mail_log' => [
        'model_label' => 'Journal du Destinataire',
        'plural_model_label' => 'Destinataires',
    ],
    
    'mail_campaign' => [
        'model_label' => 'Campagne de Mailing',
        'plural_model_label' => 'Historique des Mails',
    ],

    'defaults' => [
        'region' => 'Flandre',
    ],

    'unsubscribe' => [
        'link' => 'Se désabonner',
        'text' => 'Vous ne souhaitez plus recevoir ces e-mails ?',
        'success_title' => 'Désabonné',
        'success_body' => 'Vous avez été désabonné avec succès de notre liste de diffusion.',
        'confirmation_button' => 'Confirmer la désinscription',
    ],
];
