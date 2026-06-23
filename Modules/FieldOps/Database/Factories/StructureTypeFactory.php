<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\StructureType;

class StructureTypeFactory extends Factory
{
    protected $model = StructureType::class;

    public function definition(): array
    {
        $word = $this->faker->word();

        return [
            'created_by_user_id' => null,
            'name' => [
                'nl' => $word,
                'en' => $word,
                'fr' => $word,
                'es' => $word,
            ],
        ];
    }
}
