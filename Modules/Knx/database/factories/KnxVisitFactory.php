<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxVisit;

/**
 * @extends Factory<KnxVisit>
 */
class KnxVisitFactory extends Factory
{
    protected $model = KnxVisit::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'type' => KnxVisit::TYPE_VISIT_END,
            'work_done' => $this->faker->sentence(),
            'minutes' => 120,
            'captured_at' => now(),
            'closed_by_employee_id' => KnxEmployee::factory(),
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    /** A closure with no time reported, which is what older ones look like. */
    public function withoutMinutes(): static
    {
        return $this->state(fn (): array => ['minutes' => null]);
    }
}
