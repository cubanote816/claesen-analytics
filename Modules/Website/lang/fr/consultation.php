<?php

declare(strict_types=1);

// CLA-705: acknowledgement e-mail sent to the visitor after the website contact form.
return [
    'confirmation' => [
        'subject' => 'Nous avons bien reçu votre message (:reference)',
        'greeting' => 'Bonjour :name,',
        'received' => 'Merci pour votre message. Nous l’avons bien reçu et vous n’avez rien d’autre à faire.',
        'reference' => 'Votre numéro de référence est :reference. N’hésitez pas à le mentionner si vous nous contactez à ce sujet.',
        'next' => 'Un collaborateur examine votre demande et vous recontactera dans les meilleurs délais.',
        'urgent' => 'C’est urgent ? Appelez-nous au :phone.',
        'your_message' => 'Votre message',
        'reply' => 'Vous souhaitez ajouter quelque chose ? Répondez simplement à cet e-mail.',
        'sign_off' => 'Cordialement,',
    ],
];
