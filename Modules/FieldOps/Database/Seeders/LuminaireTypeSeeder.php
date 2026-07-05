<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

// Ported from the deprecated satellite (api-claesen-sport-app). The old seeder
// hardcoded luminaire_subgroup_id (1, 1, 4, 6) referencing its own numbering —
// looked up by brand here instead, since ids aren't guaranteed to match after
// LuminaireSubgroupSeeder re-creates them in this app. Requires
// LuminaireSubgroupSeeder to have run first. 'image' left null: the old seeder
// pointed at static asset URLs on the satellite's own server.
class LuminaireTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'BVP525 OptiVision LED Gen2', 'brand' => 'Philips Optivision LED'],
            ['name' => 'BVP527 Optivision LED Gen3', 'brand' => 'Philips Optivision LED'],
            ['name' => 'MVP507 OptiVision HID', 'brand' => 'Philips Optivision'],
            ['name' => 'MVF024 PowerVision HID', 'brand' => 'Philips PowerVision'],
        ];

        foreach ($types as $type) {
            $subgroup = LuminaireSubgroup::where('brand', $type['brand'])->first();

            if (!$subgroup) {
                continue;
            }

            LuminaireType::firstOrCreate([
                'name'                   => $type['name'],
                'luminaire_subgroup_id'  => $subgroup->id,
            ]);
        }
    }
}
