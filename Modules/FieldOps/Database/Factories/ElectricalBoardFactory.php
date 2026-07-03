<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\ElectricalBoard;

class ElectricalBoardFactory extends Factory
{
    protected $model = ElectricalBoard::class;

    public function definition(): array
    {
        return [
            'created_by_user_id'        => null,
            'electrical_board_type_id'  => ElectricalBoardTypeFactory::new(),
            'lat'                       => $this->faker->latitude(49, 52),
            'lng'                       => $this->faker->longitude(2, 6),
            'location_description'      => null,
        ];
    }
}
