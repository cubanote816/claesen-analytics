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
        'subject' => 'Passwort zurücksetzen — :company',
        'greeting' => 'Guten Tag :name,',
        'line_request' => 'Wir haben eine Anfrage zum Zurücksetzen Ihres Passworts erhalten. Dieser Link läuft in 60 Minuten ab.',
        'action' => 'Passwort zurücksetzen',
        'line_ignore' => 'Wenn Sie dies nicht angefordert haben, können Sie diese E-Mail ignorieren — Ihr Passwort bleibt unverändert.',
        // Own greeting and salutation on purpose: Laravel's are its own templates —
        // the default greeting is "Hello!" (a key nobody translates) and the
        // salutation prints `config('app.name')`, which on a two-company instance
        // cannot be both companies' name.
        'salutation' => 'Mit freundlichen Grüßen,',
    ],
    // CLA-685: welcome email of an External worker; the 72 comes from
    // PasswordResetService::WELCOME_CODE_TTL_MINUTES.
    'account_activation' => [
        'subject' => 'Passwort erstellen — :company',
        'greeting' => 'Guten Tag :name,',
        'line_intro' => 'Für Sie wurde ein Konto im Backoffice von :company erstellt. Verwenden Sie die Schaltfläche unten, um Ihr Passwort festzulegen.',
        'action' => 'Passwort erstellen',
        'line_validity' => 'Dieser Link ist :hours Stunden gültig und kann nur einmal verwendet werden.',
        'line_network' => 'Das Backoffice ist nur über das private Netzwerk erreichbar: Verbinden Sie sich über VPN oder das Büronetzwerk.',
        'line_expired' => 'Wenn der Link abgelaufen ist, bitten Sie einen Administrator, die Aktivierungs-E-Mail erneut zu senden.',
        'salutation' => 'Mit freundlichen Grüßen,',
    ],
    'sign_in_code' => [
        'greeting' => 'Guten Tag :name,',
        'salutation' => 'Mit freundlichen Grüßen,',
    ],
];
