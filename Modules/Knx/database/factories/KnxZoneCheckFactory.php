<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;

/**
 * @extends Factory<KnxZoneCheck>
 */
class KnxZoneCheckFactory extends Factory
{
    protected $model = KnxZoneCheck::class;

    public function definition(): array
    {
        return [
            'zone_id' => KnxZone::factory(),
            'key' => fake()->randomElement(KnxZoneCheck::keys()),
            'status' => KnxZoneCheck::STATUS_PENDING,
        ];
    }

    public function passed(): static
    {
        return $this->state(fn (): array => ['status' => KnxZoneCheck::STATUS_PASSED, 'updated_at' => now()]);
    }

    public function failed(?string $note = 'DALI-driver niet geleverd'): static
    {
        return $this->state(fn (): array => [
            'status' => KnxZoneCheck::STATUS_FAILED,
            'note' => $note,
            'updated_at' => now(),
        ]);
    }
}
