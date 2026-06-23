<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\FoClient;

class FoClientFactory extends Factory
{
    protected $model = FoClient::class;

    public function definition(): array
    {
        return [
            'name'     => $this->faker->company(),
            'city'     => $this->faker->city(),
            'street'   => $this->faker->streetAddress(),
            'phone'    => $this->faker->phoneNumber(),
            'email'    => $this->faker->companyEmail(),
            'language' => 'nl',
        ];
    }
}
