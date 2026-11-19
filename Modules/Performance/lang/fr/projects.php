<?php

return [
    'navigation_label'   => 'Projets',
    'model_label'        => 'Projet',
    'plural_model_label' => 'Projets',
    'columns' => [
        'name'                => 'Nom du projet',
        'manager'             => 'Chef de projet',
        'worked_total'        => 'Travaillé (total)',
        'pending_balance'     => 'Solde en attente',
        'status'              => 'Statut',
    ],
    'filters' => [
        'only_active'         => 'Actifs uniquement',
        'worked_this_month'   => 'Travaillé ce mois-ci',
        'pending_collections' => 'Recouvrements en attente',
    ],];
