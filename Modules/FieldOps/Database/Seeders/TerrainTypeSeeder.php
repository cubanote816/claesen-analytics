<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\TerrainType;

// Ported from the deprecated satellite (api-claesen-sport-app) — original data
// also had an 'es' locale, dropped per FO-008 (canonical FieldOps locales are
// nl/en/fr/de). 'de' values are new, not present in the old seeder. Note the
// translatable attribute here is 'type', not 'name' (see TerrainType model).
class TerrainTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['en' => 'Soccer', 'nl' => 'Voetbal', 'fr' => 'Football', 'de' => 'Fußball'],
            ['en' => 'Tennis', 'nl' => 'Tennis', 'fr' => 'Tennis', 'de' => 'Tennis'],
            ['en' => 'Athletics', 'nl' => 'Atletiek', 'fr' => 'Athlétisme', 'de' => 'Leichtathletik'],
        ];

        foreach ($types as $type) {
            TerrainType::firstOrCreate(['type->en' => $type['en']], ['type' => $type]);
        }
    }
}
