<?php

return [
    'cluster_label' => 'Site Web',
    'v1_demo_link' => 'Voir le site V1 (Démo)',
    'safety_pwa_link' => 'Claesen sécurité',
    'projects' => [
        'label' => 'Projet',
        'plural_label' => 'Projets',
        'sections' => [
            'details' => 'Détails du projet',
            'media' => 'Médias',
            'settings' => 'Paramètres',
        ],
        'fields' => [
            'title' => 'Titre',
            'slug' => 'Slug',
            'category' => 'Catégorie',
            'client' => 'Client',
            'location' => 'Emplacement',
            'year' => 'Année',
            'description' => 'Description',
            'published' => 'Publié',
            'featured' => 'En vedette',
            'order_index' => 'Ordre',
            'order_index_helper' => 'Détermine la position sur le site (1 = premier, 0 = par défaut ou en bas).',
            'featured_image' => 'Image à la une',
            'gallery' => 'Galerie',
        ],
    ],
    'pages' => [
        'label' => 'Page',
        'plural_label' => 'Pages',
        'sections' => [
            'content' => 'Contenu',
            'seo' => 'SEO',
            'settings' => 'Paramètres',
        ],
        'fields' => [
            'title' => 'Titre',
            'slug' => 'Slug',
            'content' => 'Contenu',
            'meta_description' => 'Méta-description',
            'meta_keywords' => 'Mots-clés méta',
            'published' => 'Publié',
            'published_at' => 'Publié le',
            'order_index' => 'Ordre',
        ],
    ],
    'consultation_requests' => [
        'label' => 'Demande de consultation',
        'plural_label' => 'Demandes de consultation',
        'sections' => [
            'contact' => 'Coordonnées',
            'details' => 'Détails de la demande',
            'management' => 'Gestion interne',
            'message' => 'Message',
            'status' => 'Statut',
        ],
        'fields' => [
            'name' => 'Nom',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'company' => 'Société',
            'type' => 'Type',
            'project_type' => 'Type de projet',
            'message' => 'Message',
            'status' => 'Statut',
            'priority' => 'Priorité',
            'assigned_to' => 'Attribué à',
            'follow_up_date' => 'Date de suivi',
            'internal_notes' => 'Notes internes',
            'tags' => 'Étiquettes',
            'first_response_sla' => 'Première réponse',
        ],
        'categories' => [
            'sport' => 'Sport',
            'industrial' => 'Industriel',
            'public' => 'Public',
        ],
        'types' => [
            'consultation' => 'Consultation',
            'quote' => 'Devis',
            'project' => 'Projet',
        ],
        'project_types' => [
            'sport' => 'Sport',
            'industrial' => 'Industriel',
            'public' => 'Public',
            'masts' => 'Mâts',
            'other' => 'Autre',
        ],
        'status_options' => [
            'new' => 'Nouveau',
            'assigned' => 'Attribué',
            'in_progress' => 'En cours',
            'waiting_client' => 'En attente du client',
            'closed' => 'Clôturé',
            'spam' => 'Spam',
        ],
        'priority_options' => [
            'low' => 'Faible',
            'medium' => 'Moyen',
            'high' => 'Élevé',
            'urgent' => 'Urgent',
        ],
        'actions' => [
            'export' => 'Exporter en CSV',
            'erase' => 'Effacer (RGPD)',
        ],
    ],
    'activities' => [
        'label' => 'Activité',
        'plural_label' => 'Activités',
        'sections' => [
            'general' => 'Général',
        ],
        'fields' => [
            'title' => 'Titre',
            'type' => 'Type',
            'user' => 'Utilisateur',
            'date' => 'Date',
            'description' => 'Description',
            'old_value' => 'Ancienne valeur',
            'new_value' => 'Nouvelle valeur',
        ],
        'types' => [
            'status_change' => 'Changement de statut',
            'priority_change' => 'Changement de priorité',
            'assignment_change' => 'Attribution',
            'follow_up_update' => 'Suivi mis à jour',
            'comment' => 'Commentaire',
            'created' => 'Créé',
            'reminder_triggered' => 'Rappel déclenché',
        ],
        'logs' => [
            'created' => 'Nouvelle demande de consultation reçue via :source',
            'status_change' => 'Statut modifié de :old à :new',
            'priority_change' => 'Priorité mise à jour : :priority',
            'assignment_change' => 'Attribué à :user',
            'follow_up_update' => 'Date de suivi fixée au :date',
            'comment' => 'Notes internes mises à jour',
            'reminder_triggered' => 'Rappel déclenché : :title',
        ],
        'notifications' => [
            'new_request_title' => 'Nouvelle demande de consultation',
            'new_request_body' => ':name a soumis une nouvelle demande.',
            'reminder_due_title' => 'Rappel échu',
            'reminder_due_body' => ':title — :name',
        ],
    ],
    'translation_review' => [
        'label' => 'Révision des traductions',
        'fields' => [
            'subject' => 'Sujet',
            'attribute' => 'Champ',
            'locale' => 'Langue',
            'status' => 'Statut',
            'error' => 'Erreur',
            'updated_at' => 'Mis à jour',
        ],
        'actions' => [
            'edit' => 'Modifier',
            'edit_value' => 'Traduction (:locale)',
            'approve' => 'Approuver',
            'publish' => 'Publier',
            'retranslate' => 'Retraduire (IA)',
            'retranslate_confirm' => "La valeur approuvée sera remplacée par une nouvelle traduction IA et devra être approuvée à nouveau. Continuer ?",
        ],
        'notifications' => [
            'updated' => 'Statut de traduction mis à jour',
        ],
    ],

    'announcements' => [
        'label' => 'Annonce',
        'plural_label' => 'Annonces',
        'message_helper' => 'Affiché dans la langue actuelle de cette vue d\'administration.',
        'starts_at_helper' => 'Vide = actif immédiatement une fois publié.',
        'ends_at_helper' => 'Vide = reste actif jusqu\'à archivage manuel.',
        'status' => [
            'draft' => 'Brouillon',
            'published' => 'Publié',
            'archived' => 'Archivé',
        ],
    ],
    // WU4 : qui a publié, quand, et ce qui a changé. Le « ce qui a changé » vient du journal
    // d'activité qui existait déjà, limité à ce qui suit la dernière publication acceptée.
    'publication_history' => [
        'label' => 'Publications',
        'changes_heading' => 'Modifications depuis la dernière publication',
        'empty' => 'Aucune publication pour le moment',
        'empty_changes' => 'Aucune modification depuis la dernière publication',
        'fields' => [
            'when'           => 'Quand',
            'who'            => 'Qui',
            'event'          => 'Quoi',
            'reason'         => 'Motif',
            'error'          => 'Erreur',
            'subject'        => 'Élément',
            'fields_changed' => 'Champs modifiés',
        ],
        'events' => [
            'requested' => 'Demandée',
            'accepted'  => 'Acceptée',
            'failed'    => 'Échouée',
        ],
        'subjects' => [
            'sitesetting'  => 'Paramètre',
            'project'      => 'Projet',
            'mediaslot'    => 'Emplacement média',
            'announcement' => 'Annonce',
        ],
    ],
    'publication' => [
        'status' => [
            'idle'     => 'Aucune modification',
            // « En attente » (l'ancien texte) décrivait le modèle automatique : depuis la
            // publication délibérée (2026-10-06), l'état signifie que des modifications
            // attendent une décision humaine, pas qu'un rebuild est en file.
            'pending'  => 'Modifications non publiées',
            'accepted' => 'Publication demandée',
            'error'    => 'Erreur de publication',
        ],
        'widget' => [
            'status_label'  => 'Statut de publication',
            'last_accepted' => 'Dernière demande',
            'build_status'  => 'Statut de construction du frontend',
            'building'      => 'Construction en cours...',
            'unreachable'   => 'Injoignable',
            'no_data'       => '—',
            'release'       => 'Version : :release',
        ],
        'actions' => [
            'publish_now'                => 'Publier maintenant',
            'publish_now_confirm_title'  => 'Lancer la publication',
            'publish_now_confirm_body'   => 'Cela demande une reconstruction complète du site. La version actuelle reste en ligne jusqu\'à la fin de la construction.',
            'publish_now_success'        => 'Publication demandée',
        ],
    ],
    // WU-S2 : la découpe des paramètres du site en six pages, telle que le prototype approuvé
    // la déclare.
    // WU-S1 : les quatre groupes du sous-menu Website, du prototype. La valeur doit être la MÊME
    // entre les éléments d'un groupe (Filament groupe par texte), donc elle se résout en un seul
    // endroit.
    'nav_groups' => [
        'publishing'    => 'Publication',
        'content'       => 'Contenu',
        'site_settings' => 'Paramètres du site',
        'inbox'         => 'Boîte de réception',
    ],
    'settings_sections' => [
        'general' => [
            'label' => 'Général',
            'description' => 'Identité légale utilisée dans le pied de page, les pages légales et les données de recherche.',
        ],
        'contact' => [
            'label' => 'Contact',
            'description' => 'Téléphone, WhatsApp et e-mail affichés sur le site.',
        ],
        'address' => [
            'label' => 'Adresse et cartes',
            'description' => 'Où se trouve le magasin et comment les visiteurs le trouvent.',
        ],
        'hours' => [
            'label' => 'Horaires d\'ouverture',
            'description' => 'Horaire hebdomadaire affiché dans le pied de page et sur la page de contact.',
        ],
        'social' => [
            'label' => 'Liens sociaux',
            'description' => 'Profils liés depuis le pied de page.',
        ],
        'privacy' => [
            'label' => 'Confidentialité et consentement',
            'description' => 'Intégrations dépendantes des cookies et suivi du consentement.',
        ],
    ],
    'site_settings' => [

        // L'adresse s'édite par parties, pas en JSON : c'est une forme fixe, et une faute de
        // frappe dans un blob JSON reste du JSON valide, donc elle passe.
        'address' => [
            'street' => 'Rue et numéro',
            'postal_code' => 'Code postal',
            'city' => 'Commune',
            'country_code' => 'Code pays (2 lettres)',
        ],
        'label' => 'Paramètres du site',
        'save' => 'Enregistrer',
        'saved' => 'Paramètres enregistrés',
        // Les horaires s'éditent jour par jour, pas en JSON : `hours: null` EST
        // « fermé » dans le contrat, donc l'interrupteur en découle.
        'opening_hours' => [
            'day' => 'Jour',
            'closed' => 'Fermé',
            'ranges' => 'Horaires d\'ouverture',
            'opens' => 'De',
            'closes' => 'À',
            'add_day' => 'Ajouter un jour',
            'add_range' => 'Ajouter une plage',
            // Les clés sont des données du contrat ; seul le libellé est traduit.
            'days' => [
                'sunday' => 'Dimanche',
                'monday' => 'Lundi',
                'tuesdayToFriday' => 'Du mardi au vendredi',
                'saturday' => 'Samedi',
            ],
        ],
        'fields' => [
            'hours' => 'Horaires d\'ouverture',
            'phone' => 'Téléphone',
            'email' => 'E-mail',
            'address' => 'Adresse',
            'social_links' => 'Liens réseaux sociaux (JSON)',
            'legal_name' => 'Raison sociale',
            'founded_year' => 'Année de fondation',
            'phone_display' => 'Téléphone (affichage)',
            'phone_tel' => 'Téléphone (lien tel:)',
            'whatsapp_display' => 'WhatsApp (affichage)',
            'whatsapp_url' => 'WhatsApp (URL)',
            'address_structured' => 'Adresse (structurée)',
            'address_country' => 'Pays (traduisible)',
            'maps_embed_url' => 'Maps intégré (URL)',
            'maps_directions_url' => 'Maps itinéraire (URL)',
            'maps_consent_mode' => 'Consentement Maps',
            'vat_number' => 'Numéro de TVA',
            'opening_hours' => 'Horaires d\'ouverture (structurés)',
            'contact_consent_version' => 'Version du consentement (formulaire contact)',
        ],
        'hints' => [
            'address' => 'Claesen : chaîne libre, lue telle quelle par le frontend Claesen. Ne pas modifier pour Electro Bertels.',
            'address_structured' => 'Electro Bertels : source structurée — JSON avec street, postal_code, city, country_code. Le frontend Claesen ne lit pas ce champ.',
            'founded_year' => 'Entier JSON, ex. 1977',
            'opening_hours' => 'Une ligne par jour. "Fermé" signifie : aucune heure d\'ouverture.',
            'maps_consent_mode' => 'always | on_consent | never',
        ],
    ],
];
