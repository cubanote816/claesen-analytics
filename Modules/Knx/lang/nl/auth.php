<?php

return [
    // Deliberately one message for every failure reason (unknown account, wrong
    // password, inactive, no role, no person row): the endpoint must not reveal
    // which of them applied.
    //
    // It must also not name an app. `POST /auth/login` serves both apps, so
    // saying "toegang tot Kantoor" told a field technician about an app they were
    // not using (CLA-627).
    'failed' => 'Deze inloggegevens kloppen niet.',
    'refresh_invalid' => 'Deze sessie is verlopen. Meld je opnieuw aan.',
];
