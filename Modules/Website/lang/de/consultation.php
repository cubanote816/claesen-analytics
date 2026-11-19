<?php

declare(strict_types=1);

// CLA-705: acknowledgement e-mail sent to the visitor after the website contact form.
return [
    'confirmation' => [
        'subject' => 'Wir haben Ihre Nachricht erhalten (:reference)',
        'greeting' => 'Guten Tag :name,',
        'received' => 'Vielen Dank für Ihre Nachricht. Sie ist bei uns eingegangen und Sie müssen nichts weiter tun.',
        'reference' => 'Ihre Referenznummer lautet :reference. Bitte geben Sie sie an, wenn Sie uns zu dieser Anfrage kontaktieren.',
        'next' => 'Ein Mitarbeiter prüft Ihre Anfrage und meldet sich so schnell wie möglich bei Ihnen.',
        'urgent' => 'Dringend? Rufen Sie uns an: :phone.',
        'your_message' => 'Ihre Nachricht',
        'reply' => 'Möchten Sie etwas ergänzen? Antworten Sie einfach auf diese E-Mail.',
        'sign_off' => 'Mit freundlichen Grüßen',
    ],
];
