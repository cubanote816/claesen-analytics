<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxExport;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxExport>
 */
class KnxExportFactory extends Factory
{
    protected $model = KnxExport::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'type' => fake()->randomElement(KnxExport::TYPES),
            'status' => KnxExport::STATUS_READY,
            'path' => 'knx/exports/'.fake()->uuid().'.pdf',
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => ['status' => KnxExport::STATUS_QUEUED, 'path' => null]);
    }
}
