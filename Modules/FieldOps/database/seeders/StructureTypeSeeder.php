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
            ['en' => 'Conical', 'nl' => 'Conische mast', 'fr' => 'Conique', 'de' => 'Konischer Mast', 'code' => 'conical', 'pin_color' => '#f5a524'],
            ['en' => 'Hinged', 'nl' => 'Vakwerk mast', 'fr' => 'À charnière', 'de' => 'Gittermast', 'code' => 'hinged', 'pin_color' => '#f5a524'],
            ['en' => 'Roof', 'nl' => 'Dakconstructie', 'fr' => 'Toit', 'de' => 'Dachkonstruktion', 'code' => 'roof', 'pin_color' => '#f5a524'],
            ['en' => 'Other', 'nl' => 'Andere', 'fr' => 'Autre', 'de' => 'Andere', 'code' => null, 'pin_color' => null],
        ];

        // updateOrCreate (not firstOrCreate) so re-running this seeder backfills
        // code/pin_color on rows created before CLA-277, not just brand-new ones.
        foreach ($types as $type) {
            ['code' => $code, 'pin_color' => $pinColor] = $type;
            $name = collect($type)->only(['en', 'nl', 'fr', 'de'])->all();

            StructureType::updateOrCreate(
                ['name->en' => $name['en']],
                ['name' => $name, 'code' => $code, 'pin_color' => $pinColor],
            );
        }
    }
}
