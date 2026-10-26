<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;

/**
 * @extends Factory<KnxZone>
 *
 * Creating a zone always creates its eight checks in `pending`, because the API
 * guarantees "always the 8 keys, in order" — a zone without them would be a
 * half-built row that no reader expects.
 */
class KnxZoneFactory extends Factory
{
    protected $model = KnxZone::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'name' => fake()->randomElement(['Vergaderzaal', 'Gang', 'Receptie', 'Technische ruimte']),
            'floor' => 'Gelijkvloers',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (KnxZone $zone): void {
            foreach (KnxZoneCheck::keys() as $key) {
                KnxZoneCheck::firstOrCreate(
                    ['zone_id' => $zone->id, 'key' => $key],
                    ['status' => KnxZoneCheck::STATUS_PENDING],
                );
            }
        });
    }
}
