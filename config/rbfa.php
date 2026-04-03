<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RBFA Series Catalog
    |--------------------------------------------------------------------------
    |
    | Grouping of Series IDs (CHP_XXXXXX) by Belgian Provinces to allow
    | for targeted or national synchronization of prospects.
    |
    | This catalog includes 1st, 2nd, 3rd, and 4th Provincial divisions
    | to ensure 100% coverage of clubs with their own grounds.
    |
    */
    'provinces' => [
        'Antwerpen' => [
            // --- Fútbol Senior Masculino ---
            'CHP_123322', // 1 Provinciaal Antw
            'CHP_123327', // 2 Provinciaal Antw A
            'CHP_123328', // 2 Provinciaal Antw B
            'CHP_124185', // 3 Provinciaal Antw A
            'CHP_123996', // 3 Provinciaal Antw B
            'CHP_123997', // 3 Provinciaal Antw C
            'CHP_125627', // 4 Provinciaal Antw A
            'CHP_125628', // 4 Provinciaal Antw B
            'CHP_125629', // 4 Provinciaal Antw C
            'CHP_125630', // 4 Provinciaal Antw D
            'CHP_125631', // 4 Provinciaal Antw E
            'CHP_125632', // 4 Provinciaal Antw F
            'CHP_129553', // 4 Provinciaal Antw G

            // --- Fútbol Femenino ---
            'CHP_123767', // Vrouwen 1 Prov Antw
            'CHP_123772', // Vrouwen 2 Prov Antw A
            'CHP_127195', // Vrouwen 2 Prov Antw B
            'CHP_123862', // Vrouwen 3 Prov Antw A
            'CHP_123863', // Vrouwen 3 Prov Antw B
        ],
        'Limburg' => [
            // --- Fútbol Senior Masculino ---
            'CHP_123324', // 1 Provinciaal Limb
            'CHP_123331', // 2 Provinciaal Limb A
            'CHP_123332', // 2 Provinciaal Limb B
            'CHP_124002', // 3 Provinciaal Limb A
            'CHP_124003', // 3 Provinciaal Limb B
            'CHP_124004', // 3 Provinciaal Limb C
            'CHP_125639', // 4 Provinciaal Limb A
            'CHP_125744', // 4 Provinciaal Limb B
            'CHP_125443', // 4 Provinciaal Limb C
            'CHP_125444', // 4 Provinciaal Limb D

            // --- Fútbol Femenino ---
            'CHP_123769', // Vrouwen 1 Prov Limb
            'CHP_123774', // Vrouwen 2 Prov Limb
        ],
        'Oost-Vlaanderen' => [
            // --- Fútbol Senior Masculino ---
            'CHP_123325', // 1 Provinciaal Ovl
            'CHP_124113', // 2 Provinciaal Ovl A
            'CHP_124114', // 2 Provinciaal Ovl B
            'CHP_124115', // 2 Provinciaal Ovl C
            'CHP_124005', // 3 Provinciaal Ovl A
            'CHP_124006', // 3 Provinciaal Ovl B
            'CHP_124007', // 3 Provinciaal Ovl C
            'CHP_124008', // 3 Provinciaal Ovl D
            'CHP_124009', // 3 Provinciaal Ovl E
            'CHP_125445', // 4 Provinciaal Ovl A
            'CHP_125446', // 4 Provinciaal Ovl B
            'CHP_125447', // 4 Provinciaal Ovl C
            'CHP_125448', // 4 Provinciaal Ovl D
            'CHP_125449', // 4 Provinciaal Ovl E
            'CHP_129554', // 4 Provinciaal Ovl F

            // --- Fútbol Femenino ---
            'CHP_123770', // Vrouwen 1 Prov Ovl
            'CHP_123775', // Vrouwen 2 Prov Ovl A
            'CHP_123776', // Vrouwen 2 Prov Ovl B
            'CHP_123866', // Vrouwen 3 Prov Ovl A
            'CHP_123867', // Vrouwen 3 Prov Ovl B
        ],
        'West-Vlaanderen' => [
            'CHP_123326', // 1 Provinciaal Wvl
            'CHP_124116', // 2 Provinciaal Wvl A
            'CHP_124117', // 2 Provinciaal Wvl B
            'CHP_124010', // 3 Provinciaal Wvl A
            'CHP_124011', // 3 Provinciaal Wvl B
            'CHP_124012', // 3 Provinciaal Wvl C
            'CHP_125450', // 4 Provinciaal Wvl A
            'CHP_125451', // 4 Provinciaal Wvl B
            'CHP_125452', // 4 Provinciaal Wvl C
            'CHP_125453', // 4 Provinciaal Wvl D
            'CHP_126078', // 4 Provinciaal Wvl E
            'CHP_123771', // Vrouwen 1 Prov Wvl
            'CHP_123777', // Vrouwen 2 Prov Wvl
            'CHP_123869', // Vrouwen 3 Prov Wvl
        ],
        'Vlaams-Brabant' => [
            // 'CHP_123323', // 1ste Prov (VV)
            // 'CHP_123335',
            // 'CHP_123336', // 2de Prov
            // 'CHP_123355',
            // 'CHP_123356',
            // 'CHP_123357', // 3de Prov
            // 'CHP_123395',
            // 'CHP_123396',
            // 'CHP_123397',
            // 'CHP_123398', // 4de Prov
            // 'CHP_123329',
            // 'CHP_123330',
            // 'CHP_123998',
            // 'CHP_123999',
            // 'CHP_124000',
            // 'CHP_124001',
            // 'CHP_125633',
            // 'CHP_125634',
            // 'CHP_125635',
            // 'CHP_125636',
            // 'CHP_125637',
            // 'CHP_125638',
        ],
        'Brabant Wallon' => [
            'CHP_127764', // 3 Prov. A
            'CHP_127765', // 3 Prov. B
            'CHP_127586', // 3 Prov. C
            'CHP_127754', // 3 Prov. D
        ],
        'Hainaut' => [
            // --- Ligas Senior Masculinas ---
            'CHP_127797', // 1 Prov.
            'CHP_127798', // 2 Prov. A
            'CHP_127799', // 2 Prov. B
            'CHP_128021', // 2 Prov. C
            'CHP_128022', // 3 Prov. A
            'CHP_128947', // 3 Prov. B
            'CHP_129077', // 3 Prov. C
            'CHP_129078', // 3 Prov. D
            'CHP_129079', // 4 Prov. A
            'CHP_129080', // 4 Prov. B
            'CHP_127641', // 4 Prov. C
            'CHP_129098', // 4 Prov. D
            'CHP_129312', // 4 Prov. E
            'CHP_129313', // 4 Prov. F
            'CHP_128400', // 4 Prov. G
            'CHP_127386', // 4 Prov. H

            // --- Ligas Femeninas ---
            'CHP_127551', // Dames 1 Prov
            'CHP_128987', // Dames 2 Prov A
            'CHP_127786', // Dames 2 Prov B
            'CHP_128112', // Dames 3 Prov A
            'CHP_128111', // Dames 3 Prov B
        ],
        'Liège' => [
            'CHP_127953', // 1ère Provinciale
            'CHP_127954', // II Provinciale A
            'CHP_127955', // II Provinciale B
            'CHP_127956', // II Provinciale C
        ],
        'Luxembourg' => [
            // --- Ligas Senior Masculinas (Provinciales) ---
            'CHP_127598', // 1 PROVINCIALE
            'CHP_128498', // 2 PROVINCIALE A
            'CHP_127390', // 2 PROVINCIALE B
            'CHP_128580', // 2 PROVINCIALE C
            'CHP_127391', // 3 PROVINCIALE A
            'CHP_128017', // 3 PROVINCIALE B
            'CHP_127392', // 3 PROVINCIALE C
            'CHP_127393', // 3 PROVINCIALE D
            'CHP_127394', // 3 PROVINCIALE E

            // --- Ligas Femeninas ---
            'CHP_127877', // DAMES 1 PROV.
            'CHP_128104', // DAMES 2 PROV.
        ],
        'Namur' => [
            // --- Ligas Senior Masculinas ---
            'CHP_127352', // 1 Prov.
            'CHP_127353', // 2 Prov.A
            'CHP_127354', // 2 Prov.B
            'CHP_127355', // 3 Prov.A
            'CHP_127356', // 3 Prov.B
            'CHP_127357', // 3 Prov.C
            'CHP_128654', // 4 Prov.A
            'CHP_128655', // 4 Prov.B
            'CHP_128781', // 4 Prov.C
            'CHP_127358', // 4 Prov.D

            // --- Ligas Femeninas ---
            'CHP_127473', // Dames 1 PROV
            'CHP_127474', // DAMES 2 PROV A
            'CHP_127475', // DAMES 2 PROV B
        ],
        'Brussel' => [
            // 'CHP_123321',
            // 'CHP_123333',
            // 'CHP_123335',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Region Keywords for Auto-Discovery (Legacy/Fallback)
    |--------------------------------------------------------------------------
    */
    'regions' => [
        'Antwerpen'       => ['Antwerpen', 'Antw'],
        'Limburg'         => ['Limburg', 'Limb'],
        'Oost-Vlaanderen' => ['Oost-Vlaanderen', 'O-Vl'],
        'West-Vlaanderen' => ['West-Vlaanderen', 'W-Vl'],
        'Vlaams-Brabant'  => ['Vlaams-Brabant', 'Vl-Br'],
        'Brabant Wallon'  => ['Brabant Wallon', 'Br-Wa', 'Wallon'],
        'Hainaut'         => ['Hainaut', 'Henegouwen'],
        'Liège'           => ['Liège', 'Luik'],
        'Luxembourg'      => ['Luxembourg', 'Luxemburg'],
        'Namur'           => ['Namur', 'Namen'],
        'Brussel'         => ['Brussel', 'Bruxelles', 'BXL'],
    ],
];
