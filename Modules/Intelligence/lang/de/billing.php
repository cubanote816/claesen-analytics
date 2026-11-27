<?php

return [
    'navigation_label' => 'Abrechnungskontrolle',
    'title'            => 'Abrechnungskontrolle',
    'alert_in_review'  => 'Warnung in Prüfung gesetzt.',
    'alert_reopened'   => 'Warnung wieder geöffnet.',
    'notifications' => [
        'confirmed_title' => 'Warnung bestätigt.',
        'confirmed_body'  => 'Führen Sie die erforderliche Aktion in CAFCA aus und klicken Sie dann auf Erledigen.',
        'dismissed_title' => 'Warnung verworfen.',
        'dismissed_body'  => 'Verwenden Sie Wieder öffnen, falls sich dies als falsch herausstellt.',
        'resolved_title'  => 'Warnung erledigt.',
        'resolved_body'   => 'Diese Warnung zählt nicht mehr für den Monatsabschluss.',
        'guardian_title'  => 'Guardian abgeschlossen — :count Warnung(en) gefunden',
        'guardian_body'   => ':created erstellt, :updated aktualisiert, :skipped übersprungen.',
    ],
    'actions' => [
        'run_guardian'         => 'Guardian ausführen',
        'run_guardian_heading' => 'Guardian ausführen?',
        'run_guardian_desc'    => 'Führen Sie ihn nach dem Speichern von Änderungen in CAFCA oder zu Monatsbeginn aus, um den Vorzeitraum zu analysieren. Offene Warnungen werden mit den neuesten Daten aktualisiert. Bestätigte und erledigte Warnungen werden NICHT geändert.',
    ],
];
