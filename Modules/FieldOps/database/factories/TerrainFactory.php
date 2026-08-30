<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\Terrain;

class TerrainFactory extends Factory
{
    protected $model = Terrain::class;

    public function definition(): array
    {
        return [
            'complex_id'         => ComplexFactory::new(),
            'terrain_type_id'    => TerrainTypeFactory::new(),
            'created_by_user_id' => null,
            'name'               => [
                'nl' => $this->faker->words(2, true),
                'en' => $this->faker->words(2, true),
            ],
            'lat' => $this->faker->latitude(49, 52),
            'lng' => $this->faker->longitude(2, 6),
        ];
    }
}
