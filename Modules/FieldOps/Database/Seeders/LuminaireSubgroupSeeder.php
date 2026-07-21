<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireSubgroup;

class LuminaireSubgroupSeeder extends Seeder
{
    public function run(): void
    {
        $subgroups = [
            ['group_name' => 'LED', 'brand' => 'Philips / Signify'],
            ['group_name' => 'LED', 'brand' => 'Schréder'],
            ['group_name' => 'LED', 'brand' => 'Thorn'],
            ['group_name' => 'LED', 'brand' => 'Musco'],
            ['group_name' => 'HID — legacy only', 'brand' => 'Philips'],
        ];

        foreach ($subgroups as $subgroup) {
            LuminaireSubgroup::updateOrCreate($subgroup, $subgroup);
        }
    }
}
