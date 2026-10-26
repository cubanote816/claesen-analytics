<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxClient;

/**
 * @extends Factory<KnxClient>
 */
class KnxClientFactory extends Factory
{
    protected $model = KnxClient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'city' => fake()->city(),
            'contact' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress(),
            'vat' => 'BE 0'.fake()->numerify('###').'.'.fake()->numerify('###').'.'.fake()->numerify('###'),
        ];
    }
}
