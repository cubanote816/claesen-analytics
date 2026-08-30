<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;

class FoMaintenanceRecordFactory extends Factory
{
    protected $model = FoMaintenanceRecord::class;

    public function definition(): array
    {
        return [
            'created_by_user_id'     => null,
            'fo_maintenance_type_id' => FoMaintenanceTypeFactory::new(),
            'maintainable_id'        => LuminaireFactory::new(),
            'maintainable_type'      => Luminaire::class,
            'employee_id'            => null,
            'client_id'              => null,
            'maintenance_at'         => $this->faker->dateTimeBetween('-6 months', 'now'),
            'details'                => [
                'inspection'       => true,
                'cleaning'         => true,
                'component_checks' => false,
            ],
            'notes'                  => $this->faker->optional()->sentence(),
        ];
    }

    public function forMaintainable(\Illuminate\Database\Eloquent\Model $maintainable): static
    {
        return $this->state(fn () => [
            'maintainable_id'   => $maintainable->id,
            'maintainable_type' => get_class($maintainable),
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => [
            'is_emergency'         => true,
            'problem_description'  => $this->faker->sentence(),
            'problem_reported_at'  => now()->subHours(4),
        ]);
    }

    public function clientReported(): static
    {
        return $this->state(fn () => [
            'reported_by_client'  => true,
            'priority'            => 'high',
            'is_emergency'        => true,
            'problem_description' => $this->faker->sentence(),
            'problem_reported_at' => now()->subHours(2),
        ]);
    }
}
