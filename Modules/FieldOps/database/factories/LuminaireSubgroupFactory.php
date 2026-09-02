<?php

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\LuminaireSubgroup;

class LuminaireSubgroupFactory extends Factory
{
    protected $model = LuminaireSubgroup::class;

    public function definition(): array
    {
        return [
            'group_name' => $this->faker->word(),
            'brand'      => $this->faker->company(),
        ];
    }
}
