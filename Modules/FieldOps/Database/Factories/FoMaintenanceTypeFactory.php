<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\FoMaintenanceType;

class FoMaintenanceTypeFactory extends Factory
{
    protected $model = FoMaintenanceType::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => null,
            'name'               => ['nl' => $this->faker->words(2, true)],
            'code'               => null,
        ];
    }

    public function preventive(): static
    {
        return $this->state(fn () => [
            'name' => ['nl' => 'Preventief', 'en' => 'Preventive'],
            'code' => FoMaintenanceType::CODE_PREVENTIVE,
        ]);
    }

    public function corrective(): static
    {
        return $this->state(fn () => [
            'name' => ['nl' => 'Correctief', 'en' => 'Corrective'],
            'code' => FoMaintenanceType::CODE_CORRECTIVE,
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => [
            'name' => ['nl' => 'Noodgeval', 'en' => 'Emergency'],
            'code' => FoMaintenanceType::CODE_EMERGENCY,
        ]);
    }
}
