<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\FoMaintenanceType;

class FoMaintenanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            FoMaintenanceType::CODE_PREVENTIVE => [
                'nl' => 'Preventief', 'en' => 'Preventive', 'fr' => 'Préventif', 'de' => 'Präventiv',
            ],
            FoMaintenanceType::CODE_CORRECTIVE => [
                'nl' => 'Correctief', 'en' => 'Corrective', 'fr' => 'Correctif', 'de' => 'Korrektiv',
            ],
            FoMaintenanceType::CODE_EMERGENCY => [
                'nl' => 'Noodgeval', 'en' => 'Emergency', 'fr' => 'Urgence', 'de' => 'Notfall',
            ],
        ];

        foreach ($types as $code => $name) {
            FoMaintenanceType::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
