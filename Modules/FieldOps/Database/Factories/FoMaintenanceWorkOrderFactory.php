<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;

class FoMaintenanceWorkOrderFactory extends Factory
{
    protected $model = FoMaintenanceWorkOrder::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => null,
            'fo_maintenance_type_id' => FoMaintenanceTypeFactory::new()->preventive(),
            'maintainable_id' => LuminaireFactory::new(),
            'maintainable_type' => Luminaire::class,
            'assigned_employee_id' => null,
            'status' => MaintenanceWorkOrderStatus::PLANNED,
            'priority' => 'medium',
            'source' => 'backoffice',
            'scheduled_for' => now()->addDay(),
            'due_at' => now()->addDays(2),
            'instructions' => $this->faker->sentence(),
        ];
    }

    public function forMaintainable(\Illuminate\Database\Eloquent\Model $maintainable): static
    {
        return $this->state(fn (): array => [
            'maintainable_id' => $maintainable->getKey(),
            'maintainable_type' => $maintainable::class,
            'luminaire_position_id' => $maintainable instanceof Luminaire ? $maintainable->luminaire_position_id : null,
        ]);
    }
}
