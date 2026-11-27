<?php

return [
    'model_label' => 'Prospect',
    'plural_model_label' => 'Prospects',
    'navigation_group' => 'Croissance et Acquisition',

    'tabs' => [
        'real_prospects' => 'Vrais Prospects',
        'testers' => 'Testeurs (Direction)',
        'all' => 'Tous',
    ],

    'sections' => [
        'contact_history' => 'Historique des contacts',
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
        'last_contacted_at' => 'Dernier contact',
        'contacted' => 'Contacté',
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
        'contact_name' => 'Nom du Contact',
        'email' => 'E-mail',
        'phone' => 'Numéro de Téléphone',
        'address' => 'Adresse',
        'logo' => 'Logo',
        'has_email' => 'Possède une adresse E-mail',
        'locations_count' => 'Nombre d\'Emplacements',
        'command' => 'Commande',
        'status' => 'Statut',
        'started_by' => 'Démarré par',
        'logged_by' => 'Enregistré par',
        'items' => 'Articles',
        'started_at' => 'Démarré le',
        'finished_at' => 'Terminé le',
        'total_items' => 'Total des Articles traités',
        'last_activity' => 'Dernière Activité',
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
        'is_tester' => 'Testeur (Environnement de test)',
        'is_tester_helper' => 'Marquer comme prospect de test pour la direction',
        'tester' => 'Testeur',
    ],

    'options' => [
        'contact_directions' => [
            'inbound' => 'Entrant (ils nous ont contactés)',
            'outbound' => 'Sortant (nous les avons contactés)',
        ],
        'contact_methods' => [
            'phone' => 'Téléphone',
            'email' => 'E-mail',
            'in_person' => 'En personne',
            'website_form' => 'Formulaire du site web',
            'other' => 'Autre',
        ],
        'contact_status' => [
            'contacted' => 'Contacté',
            'not_contacted' => 'Non contacté',
            'never' => 'Jamais',
        ],
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
            'pending' => 'En attente...',
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
            'table_tennis_club' => 'Tennis de table',
            'sports_infrastructure' => 'Infrastructure sportive',
        ],
    ],

    'actions' => [
        'mark_contacted' => [
            'label' => 'Enregistrer un contact',
            'form' => [
                'contacted_at' => 'Date et heure',
                'direction' => 'Direction',
                'method' => 'Méthode',
                'note' => 'Note',
            ],
        ],
        'remove_contact' => [
            'label' => 'Supprimer une entrée de contact',
            'form' => [
                'entry' => 'Entrée à supprimer',
            ],
            'confirm' => 'Seule l\'entrée sélectionnée est supprimée. Les autres entrées de contact restent inchangées.',
        ],
        'execute_campaign' => [
            'label' => 'Démarrer la Campagne de Mailing',
            'view_campaign' => 'Voir la campagne',
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
        'back_to_list' => 'Retour à la Liste',
    ],

    'notifications' => [
        'contact_logged' => [
            'title' => 'Contact enregistré',
        ],
        'contact_removed' => [
            'title' => 'Entrée de contact supprimée',
        ],
        'no_prospects_selected' => [
            'title' => 'Aucun prospect sélectionné',
            'body' => 'Veuillez sélectionner au moins un prospect pour démarrer un mailing.',
        ],
        'all_prospects_unsubscribed' => [
            'title' => 'Tous les prospects sélectionnés sont désabonnés',
            'body' => 'Aucun des prospects sélectionnés ne peut recevoir des e-mails. Retirez-les d\'abord de la liste de suppression.',
        ],
        'campaign_started' => [
            'title' => 'Envoi en arrière-plan',
            'body' => 'Envoi de :count e-mails en arrière-plan. Vous pouvez continuer à travailler ; nous vous préviendrons à la fin.',
        ],
        'campaign_sent_to_review' => [
            'title' => 'Campagne Envoyée en Validation',
            'body' => 'La campagne a été créée et envoyée en validation. Un approbateur doit l\'approuver avant l\'envoi.',
        ],
        'campaign_template_invalid' => [
            'title' => 'Campagne Non Créée',
            'body' => ':reason',
        ],
        'partial_skip' => [
            'title' => 'Campagne Démarrée',
            'body' => 'Attention : :unsubscribed des :total prospects sélectionnés sont désabonnés et seront ignorés.',
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
        'sync_blocked_master' => [
            'title' => 'Master sync actif',
            'body'  => 'Attendez que le master sync se termine avant de lancer une sync individuelle.',
        ],
        'sync_already_running' => [
            'title' => 'Sync déjà en cours',
        ],
        'sync_blocked_any' => [
            'title' => 'Des syncs sont déjà en cours',
            'body'  => 'Attendez que toutes les synchronisations actives se terminent avant de lancer un master sync.',
        ],
        'no_emails_found' => [
            'title' => 'Aucune adresse e-mail trouvée',
            'body' => 'Les prospects sélectionnés n\'ont pas d\'emplacements avec des adresses e-mail enregistrées.',
        ],
        'error_mailer' => 'Erreur lors de l\'envoi de la campagne via le service de messagerie.',
    ],

    'contact_history' => [
        'empty' => 'Aucun contact n\'a encore été enregistré pour ce prospect.',
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
