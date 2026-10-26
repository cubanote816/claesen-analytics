<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxDocument>
 */
class KnxDocumentFactory extends Factory
{
    protected $model = KnxDocument::class;

    public function definition(): array
    {
        $project = KnxProject::factory();

        return [
            'project_id' => $project,
            'name' => 'plan_'.fake()->bothify('####').'.pdf',
            'kind' => fake()->randomElement(['Plan', 'Schema', 'ETS', 'Keuring', 'Foto’s']),
            'size_bytes' => fake()->numberBetween(120_000, 9_000_000),
            'path' => 'knx/documents/'.fake()->uuid().'.pdf',
            'revision' => 'Rev. '.fake()->randomLetter(),
            'is_current' => true,
            'uploaded_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function superseded(): static
    {
        return $this->state(fn (): array => ['is_current' => false]);
    }
}
