<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\AccessType;

// Ported from the deprecated satellite (api-claesen-sport-app) — original data
// also had an 'es' locale, dropped per FO-008 (canonical FieldOps locales are
// nl/en/fr/de). 'de' values are new, not present in the old seeder.
class AccessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['en' => 'No access (only Cherrypicker)', 'nl' => 'Geen toegang (enkel hoogwerker)', 'fr' => 'Pas d\'accès (nacelle uniquement)', 'de' => 'Kein Zugang (nur Hubarbeitsbühne)'],
            ['en' => 'Steps', 'nl' => 'Trapjes', 'fr' => 'Marches', 'de' => 'Stufen'],
            ['en' => 'Ladder', 'nl' => 'Ladder', 'fr' => 'Échelle', 'de' => 'Leiter'],
            ['en' => 'Other', 'nl' => 'Andere', 'fr' => 'Autre', 'de' => 'Andere'],
        ];

        foreach ($types as $name) {
            AccessType::firstOrCreate(['name->en' => $name['en']], ['name' => $name]);
        }
    }
}
