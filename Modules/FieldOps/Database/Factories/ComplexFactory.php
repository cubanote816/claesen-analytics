<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\Complex;

class ComplexFactory extends Factory
{
    protected $model = Complex::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => null,
            'client_id'          => null,
            'name'               => $this->faker->company() . ' Complex',
            'street'             => $this->faker->streetAddress(),
            'city'               => $this->faker->city(),
            'zipcode'            => $this->faker->postcode(),
            'lat'                => $this->faker->latitude(49, 52),
            'lng'                => $this->faker->longitude(2, 6),
            'zoom'               => 17.00,
        ];
    }
}
