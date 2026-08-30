<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\Luminaire;

class FoMaintenancePlanFactory extends Factory
{
    protected $model = FoMaintenancePlan::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => null,
            'fo_maintenance_type_id' => FoMaintenanceTypeFactory::new()->preventive(),
            'maintainable_id' => LuminaireFactory::new(),
            'maintainable_type' => Luminaire::class,
            'assigned_employee_id' => null,
            'recurrence_unit' => 'months',
            'recurrence_interval' => 6,
            'next_due_at' => now()->addMonths(6),
            'instructions' => 'Inspect, clean and test the installation.',
            'is_active' => true,
        ];
    }
}
