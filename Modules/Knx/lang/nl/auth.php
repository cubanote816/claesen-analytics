<?php

return [
    // Deliberately one message for every failure reason (unknown account, wrong
    // password, inactive, no role, no person row): the endpoint must not reveal
    // which of them applied.
    'failed' => 'Deze inloggegevens kloppen niet of dit account heeft geen toegang tot Kantoor.',
    'refresh_invalid' => 'Deze sessie is verlopen. Meld je opnieuw aan.',
];
