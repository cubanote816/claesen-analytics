<?php

return [
    'navigation_label' => 'Facturatiebeheer',
    'title'            => 'Facturatiebeheer',
    'alert_in_review'  => 'Melding in review geplaatst.',
    'alert_reopened'   => 'Melding heropend.',
    'notifications' => [
        'confirmed_title' => 'Melding bevestigd.',
        'confirmed_body'  => 'Voer de actie uit in CAFCA en klik daarna op Oplossen.',
        'dismissed_title' => 'Melding afgewezen.',
        'dismissed_body'  => 'Gebruik Heropenen als dit onjuist blijkt.',
        'resolved_title'  => 'Melding opgelost.',
        'resolved_body'   => 'De melding telt niet meer mee voor de maandafsluiting.',
        'guardian_title'  => 'Guardian klaar — :count alert(s) gevonden',
        'guardian_body'   => ':created nieuw, :updated bijgewerkt, :skipped overgeslagen.',
    ],
    'actions' => [
        'run_guardian'         => 'Guardian uitvoeren',
        'run_guardian_heading' => 'Guardian uitvoeren?',
        'run_guardian_desc'    => 'Voer de Guardian uit nadat u wijzigingen in CAFCA heeft opgeslagen, of aan het begin van de maand voor de analyse van de vorige periode. Open meldingen worden bijgewerkt met de laatste gegevens. Bevestigde en afgesloten meldingen worden NIET gewijzigd.',
    ],
];
