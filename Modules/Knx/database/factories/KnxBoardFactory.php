<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxBoard>
 */
class KnxBoardFactory extends Factory
{
    protected $model = KnxBoard::class;

    public function definition(): array
    {
        $code = 'E'.fake()->numberBetween(10, 29);

        return [
            'project_id' => KnxProject::factory(),
            'code' => $code,
            'name' => 'Verdeelbord '.$code,
        ];
    }
}
