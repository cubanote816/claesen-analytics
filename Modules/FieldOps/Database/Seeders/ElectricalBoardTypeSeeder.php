<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\ElectricalBoardType;

// Ported from the deprecated satellite (api-claesen-sport-app) — original data
// also had an 'es' locale, dropped per FO-008 (canonical FieldOps locales are
// nl/en/fr/de). 'de' values are new, not present in the old seeder.
class ElectricalBoardTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['en' => 'Cabinet', 'nl' => 'Kabinet', 'fr' => 'Cabinet', 'de' => 'Schrank'],
            ['en' => 'Plate in pole', 'nl' => 'Plaat in paal', 'fr' => 'Plaque en poteau', 'de' => 'Platte im Mast'],
            ['en' => 'Street cabinet', 'nl' => 'Straatkast', 'fr' => 'Armoire de rue', 'de' => 'Straßenschrank'],
        ];

        foreach ($types as $name) {
            ElectricalBoardType::firstOrCreate(['name->en' => $name['en']], ['name' => $name]);
        }
    }
}
