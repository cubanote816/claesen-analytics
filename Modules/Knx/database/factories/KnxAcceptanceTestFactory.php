<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxAcceptanceTest>
 */
class KnxAcceptanceTestFactory extends Factory
{
    protected $model = KnxAcceptanceTest::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'function_id' => KnxFunctionSpec::factory(),
            'action' => 'Betreed de vergaderzaal',
            'expected' => 'Verlichting gaat aan binnen 2 seconden',
            'status' => 'pending',
            'physical_check' => true,
        ];
    }
}
