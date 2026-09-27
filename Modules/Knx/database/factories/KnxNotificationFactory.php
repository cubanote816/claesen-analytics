<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxNotification>
 */
class KnxNotificationFactory extends Factory
{
    protected $model = KnxNotification::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'type' => 'Aanwezigheidsdetector',
            'address' => fake()->numberBetween(1, 2).'.'.fake()->numberBetween(0, 15).'.'.fake()->numberBetween(1, 255),
            'room' => 'Zaal '.fake()->numberBetween(1, 9).'.'.fake()->numberBetween(1, 20),
            'serial' => strtoupper(fake()->bothify('00##-####')),
            'reported_at' => now(),
        ];
    }

    public function acked(): static
    {
        return $this->state(fn (): array => ['acknowledged_at' => now()]);
    }
}
