<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\AccessType;

class AccessTypeFactory extends Factory
{
    protected $model = AccessType::class;

    public function definition(): array
    {
        $word = $this->faker->word();

        return [
            'created_by_user_id' => null,
            'name' => [
                'nl' => $word,
                'en' => $word,
                'fr' => $word,
                'de' => $word,
            ],
        ];
    }
}
