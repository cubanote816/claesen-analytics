<?php

return [
    'navigation_label' => 'Contrôle de la facturation',
    'title'            => 'Contrôle de la facturation',
    'alert_in_review'  => 'Alerte mise en révision.',
    'alert_reopened'   => 'Alerte rouverte.',
    'notifications' => [
        'confirmed_title' => 'Alerte confirmée.',
        'confirmed_body'  => 'Effectuez l\'action requise dans CAFCA, puis cliquez sur Résoudre.',
        'dismissed_title' => 'Alerte écartée.',
        'dismissed_body'  => 'Utilisez Rouvrir s\'il s\'avère que c\'est une erreur.',
        'resolved_title'  => 'Alerte résolue.',
        'resolved_body'   => 'Cette alerte n\'est plus comptabilisée dans la clôture mensuelle.',
        'guardian_title'  => 'Guardian terminé — :count alerte(s) trouvée(s)',
        'guardian_body'   => ':created créée(s), :updated mise(s) à jour, :skipped ignorée(s).',
    ],
    'actions' => [
        'run_guardian'         => 'Lancer le Guardian',
        'run_guardian_heading' => 'Lancer le Guardian ?',
        'run_guardian_desc'    => 'Lancez-le après avoir enregistré des modifications dans CAFCA, ou en début de mois pour analyser la période précédente. Les alertes ouvertes sont mises à jour avec les données les plus récentes. Les alertes confirmées et résolues ne sont PAS modifiées.',
    ],
];
