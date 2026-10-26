<?php

declare(strict_types=1);

// Textos de los correos que manda el núcleo. El idioma sale del propio usuario
// (`User::preferredLocale()`), no del panel ni del default de la app: en la cola no
// hay request, así que el default era siempre inglés y un usuario neerlandés recibía
// «Reset your password».
//
// El vocabulario sigue al de las pantallas de contraseña (`auth.php`, CLA-603) para
// que el correo y la pantalla digan lo mismo: «herstellink», «Wachtwoord opnieuw
// instellen».
return [
    'password_reset' => [
        'subject' => 'Wachtwoord opnieuw instellen — :company',
        'greeting' => 'Hallo :name,',
        'line_request' => 'We hebben een verzoek gekregen om je wachtwoord opnieuw in te stellen. Deze herstellink verloopt over 60 minuten.',
        'action' => 'Wachtwoord opnieuw instellen',
        'line_ignore' => 'Heb je dit niet aangevraagd? Dan kun je deze e-mail veilig negeren — je wachtwoord verandert niet.',
        // El saludo y la despedida propios existen porque los de Laravel son
        // plantillas suyas: el saludo por defecto es «Hello!» (una clave que nadie
        // traduce) y la despedida imprime `config('app.name')`, que en una instancia
        // con dos empresas no puede ser el nombre de las dos.
        'salutation' => 'Met vriendelijke groeten,',
    ],
    'sign_in_code' => [
        'greeting' => 'Hallo :name,',
        'salutation' => 'Met vriendelijke groeten,',
    ],
];
