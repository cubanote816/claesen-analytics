<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireSubgroup;

// Ported from the deprecated satellite's LuminaireGroupSeeder + LuminaireSubgroupSeeder
// combined — this app collapsed LuminaireGroup into a denormalized 'group_name'
// string on fo_luminaire_subgroups (Slice C decision, see CLAUDE.md), so there
// is no separate groups table/seeder to port.
class LuminaireSubgroupSeeder extends Seeder
{
    public function run(): void
    {
        $subgroups = [
            ['group_name' => 'LED', 'brand' => 'Philips Optivision LED'],
            ['group_name' => 'LED', 'brand' => 'Lumosa LED'],
            ['group_name' => 'LED', 'brand' => 'Schreder Omnistar'],
            ['group_name' => 'HID', 'brand' => 'Philips Optivision'],
            ['group_name' => 'HID', 'brand' => 'Philips ArenaVision'],
            ['group_name' => 'HID', 'brand' => 'Philips PowerVision'],
        ];

        foreach ($subgroups as $subgroup) {
            LuminaireSubgroup::firstOrCreate($subgroup);
        }
    }
}
