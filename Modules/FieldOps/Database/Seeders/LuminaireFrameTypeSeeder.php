<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireFrameType;

// Ported from the deprecated satellite (api-claesen-sport-app). Unlike the old
// model, LuminaireFrameType here is a plain string (no HasTranslations) — no
// locale variants to port. 'image' left null: the old seeder pointed at static
// asset URLs on the satellite's own server, which don't exist in this app.
class LuminaireFrameTypeSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Traverse 1', 'Traverse 2', 'Traverse 3', 'Traverse 4', 'Traverse 5', 'Balcony'];

        foreach ($names as $name) {
            LuminaireFrameType::firstOrCreate(['name' => $name]);
        }
    }
}
