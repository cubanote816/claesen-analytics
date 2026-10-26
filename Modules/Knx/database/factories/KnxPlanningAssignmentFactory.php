<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxPlanningAssignment>
 */
class KnxPlanningAssignmentFactory extends Factory
{
    protected $model = KnxPlanningAssignment::class;

    public function definition(): array
    {
        return [
            'employee_id' => KnxEmployee::factory()->field(),
            'project_id' => KnxProject::factory(),
            'date' => now()->startOfWeek()->format('Y-m-d'),
        ];
    }
}
