<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;

/**
 * @extends Factory<KnxProjectRoom>
 */
class KnxProjectRoomFactory extends Factory
{
    protected $model = KnxProjectRoom::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'name' => fake()->randomElement(['Vergaderzaal', 'Gang', 'Receptie', 'Zaal 2.07', 'Technische ruimte']),
            'floor' => fake()->randomElement(['Gelijkvloers', '1e verdieping', '2e verdieping']),
        ];
    }
}
