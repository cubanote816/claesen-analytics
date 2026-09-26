<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxTestExecution;

/**
 * @extends Factory<KnxTestExecution>
 */
class KnxTestExecutionFactory extends Factory
{
    protected $model = KnxTestExecution::class;

    public function definition(): array
    {
        return [
            'acceptance_test_id' => KnxAcceptanceTest::factory(),
            'status' => 'pending',
            'executed_at' => now(),
        ];
    }
}
