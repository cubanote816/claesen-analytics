<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\SafetyType;

// Ported from the deprecated satellite (api-claesen-sport-app) — original data
// also had an 'es' locale, dropped per FO-008 (canonical FieldOps locales are
// nl/en/fr/de). 'de' values are new, not present in the old seeder.
class SafetyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['en' => 'No Security', 'nl' => 'Geen beveiliging', 'fr' => 'Aucune sécurité', 'de' => 'Keine Sicherung'],
            ['en' => 'Cable', 'nl' => 'Veiligheidskabel', 'fr' => 'Câble', 'de' => 'Sicherheitskabel'],
            ['en' => 'Rail', 'nl' => 'Veiligheidsrail', 'fr' => 'Rail', 'de' => 'Schiene'],
            ['en' => 'Other', 'nl' => 'Andere', 'fr' => 'Autre', 'de' => 'Andere'],
        ];

        foreach ($types as $name) {
            SafetyType::firstOrCreate(['name->en' => $name['en']], ['name' => $name]);
        }
    }
}
