<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxDevice>
 */
class KnxDeviceFactory extends Factory
{
    protected $model = KnxDevice::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'room_id' => null,
            'board_id' => null,
            'type' => fake()->randomElement([
                'Aanwezigheidsdetector',
                'KNX-drukknop 4-voudig',
                'DALI-gateway',
                'Schakelactor 8-voudig',
                'Temperatuursensor',
            ]),
            'address' => fake()->numberBetween(1, 2).'.'.fake()->numberBetween(0, 15).'.'.fake()->numberBetween(1, 255),
            'serial' => strtoupper(fake()->bothify('00##-####')),
            'source' => KnxDevice::SOURCE_ETS,
        ];
    }

    public function fromField(): static
    {
        return $this->state(fn (): array => [
            'source' => KnxDevice::SOURCE_FIELD,
            'registered_at' => now(),
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (): array => ['acknowledged_at' => now()]);
    }
}
