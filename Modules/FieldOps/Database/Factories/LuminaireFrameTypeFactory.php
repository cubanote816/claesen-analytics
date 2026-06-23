<?php

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\LuminaireFrameType;

class LuminaireFrameTypeFactory extends Factory
{
    protected $model = LuminaireFrameType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
        ];
    }
}
