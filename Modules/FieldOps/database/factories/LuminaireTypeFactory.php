<?php

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireTypeFactory extends Factory
{
    protected $model = LuminaireType::class;

    public function definition(): array
    {
        return [
            'luminaire_subgroup_id' => LuminaireSubgroup::factory(),
            'name'                  => $this->faker->words(2, true),
        ];
    }
}
