<?php

declare(strict_types=1);

// Copy for the core's outgoing mail. The language comes from the recipient
// (`User::preferredLocale()`), never from the panel or the app default: in the queue
// there is no request, so the default won the day and a Dutch user got English.
//
// Vocabulary follows the password screens in `auth.php` so the mail and the screen
// say the same thing about the same link.
return [
    'password_reset' => [
        'subject' => 'Réinitialisez votre mot de passe — :company',
        'greeting' => 'Bonjour :name,',
        'line_request' => 'Nous avons reçu une demande de réinitialisation de votre mot de passe. Ce lien expire dans 60 minutes.',
        'action' => 'Réinitialiser le mot de passe',
        'line_ignore' => 'Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet e-mail : votre mot de passe ne sera pas modifié.',
        // Own greeting and salutation on purpose: Laravel's are its own templates —
        // the default greeting is "Hello!" (a key nobody translates) and the
        // salutation prints `config('app.name')`, which on a two-company instance
        // cannot be both companies' name.
        'salutation' => 'Cordialement,',
    ],
    // CLA-685: welcome email of an External worker; the 72 comes from
    // PasswordResetService::WELCOME_CODE_TTL_MINUTES.
    'account_activation' => [
        'subject' => 'Créez votre mot de passe — :company',
        'greeting' => 'Bonjour :name,',
        'line_intro' => 'Un compte a été créé pour vous sur le backoffice de :company. Utilisez le bouton ci-dessous pour choisir votre mot de passe.',
        'action' => 'Créer le mot de passe',
        'line_validity' => 'Ce lien est valable :hours heures et ne peut être utilisé qu\'une seule fois.',
        'line_network' => 'Le backoffice n\'est accessible que depuis le réseau privé : connectez-vous via le VPN ou le réseau du bureau.',
        'line_expired' => 'Si le lien a expiré, demandez à un administrateur de renvoyer l\'e-mail d\'activation.',
        'salutation' => 'Cordialement,',
    ],
    'sign_in_code' => [
        'greeting' => 'Bonjour :name,',
        'salutation' => 'Cordialement,',
    ],
];
