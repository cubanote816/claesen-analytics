<?php

declare(strict_types=1);

// CLA-705: acknowledgement e-mail sent to the visitor after the website contact form.
return [
    'confirmation' => [
        'subject' => 'Wij hebben uw bericht ontvangen (:reference)',
        'greeting' => 'Beste :name,',
        'received' => 'Bedankt voor uw bericht. Wij hebben het goed ontvangen en u hoeft verder niets te doen.',
        'reference' => 'Uw referentienummer is :reference. Vermeld het gerust als u ons hierover contacteert.',
        'next' => 'Een medewerker bekijkt uw vraag en neemt zo snel mogelijk contact met u op.',
        'urgent' => 'Dringend? Bel ons op :phone.',
        'your_message' => 'Uw bericht',
        'reply' => 'Wilt u nog iets toevoegen? Beantwoord dan gewoon deze e-mail.',
        'sign_off' => 'Met vriendelijke groeten,',
    ],
];
