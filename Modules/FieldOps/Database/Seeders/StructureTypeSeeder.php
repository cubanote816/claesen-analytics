<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\StructureType;

// Ported from the deprecated satellite (api-claesen-sport-app) — original data
// also had an 'es' locale, dropped per FO-008 (canonical FieldOps locales are
// nl/en/fr/de). 'de' values are new, not present in the old seeder.
class StructureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['en' => 'Conical', 'nl' => 'Conische mast', 'fr' => 'Conique', 'de' => 'Konischer Mast'],
            ['en' => 'Hinged', 'nl' => 'Vakwerk mast', 'fr' => 'À charnière', 'de' => 'Gittermast'],
            ['en' => 'Roof', 'nl' => 'Dakconstructie', 'fr' => 'Toit', 'de' => 'Dachkonstruktion'],
            ['en' => 'Other', 'nl' => 'Andere', 'fr' => 'Autre', 'de' => 'Andere'],
        ];

        foreach ($types as $name) {
            StructureType::firstOrCreate(['name->en' => $name['en']], ['name' => $name]);
        }
    }
}
