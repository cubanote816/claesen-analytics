<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\Structure;

class StructureFactory extends Factory
{
    protected $model = Structure::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => null,
            'structure_type_id'  => StructureTypeFactory::new(),
            'height'             => $this->faker->numberBetween(3, 12),
            'lat'                => $this->faker->latitude(49, 52),
            'lng'                => $this->faker->longitude(2, 6),
            'info'               => null,
            'external_safety_id' => null,
            'external_access_id' => null,
            'cafca_material_id'  => null,
        ];
    }
}
