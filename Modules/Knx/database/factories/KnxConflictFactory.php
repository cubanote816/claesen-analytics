<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxConflict>
 */
class KnxConflictFactory extends Factory
{
    protected $model = KnxConflict::class;

    public function definition(): array
    {
        $address = fake()->numberBetween(1, 2).'.'.fake()->numberBetween(0, 15).'.'.fake()->numberBetween(1, 255);

        return [
            'project_id' => KnxProject::factory(),
            'severity' => fake()->randomElement(KnxConflict::SEVERITIES),
            'type' => fake()->randomElement(KnxConflict::TYPES),
            'lifecycle' => KnxConflict::LIFECYCLE_ETS,
            'address' => $address,
            'device_existing' => 'Aanwezigheidsdetector · Vergaderzaal · rij 3',
            'device_field' => 'KNX-drukknop 4-voudig · Gang gelijkvloers · rij 5',
            'reported_at' => now(),
            'note' => fake()->sentence(),
            'status' => 'open',
            'proposal' => null,
        ];
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /**
     * A malfunction: its own cycle, and `symptom` instead of `proposal` (KNX-56).
     */
    public function malfunction(string $status = 'open'): static
    {
        return $this->state(fn (): array => [
            'lifecycle' => KnxConflict::LIFECYCLE_MALFUNCTION,
            'type' => 'malfunction',
            'status' => $status,
            'proposal' => null,
            'symptom' => 'Gaat niet aan als de aanwezigheidsmelder aanslaat.',
        ]);
    }
}
