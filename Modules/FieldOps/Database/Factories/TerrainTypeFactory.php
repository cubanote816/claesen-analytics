<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\TerrainType;

class TerrainTypeFactory extends Factory
{
    protected $model = TerrainType::class;

    public function definition(): array
    {
        $word = $this->faker->word();

        return [
            'type' => [
                'nl' => $word,
                'en' => $word,
                'fr' => $word,
                'es' => $word,
            ],
        ];
    }
}
