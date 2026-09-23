<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Site;
use Modules\Website\Models\Project;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            // F1/P3a (docs/ai/adr-multi-organization.md): resolves the seeded
            // Claesen site so the existing Website tests keep working unchanged;
            // falls back to creating it only for isolated tests that skip the
            // phase P1 seed migration.
            'site_id'     => fn () => Site::query()->where('key', Site::CLAESEN_KEY)->value('id')
                ?? Site::factory()->create(['key' => Site::CLAESEN_KEY])->id,
            'slug'        => $this->faker->unique()->slug(),
            'title'       => ['nl' => $this->faker->sentence(3), 'en' => $this->faker->sentence(3)],
            'description' => ['nl' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'category'    => $this->faker->randomElement(['sport', 'industrial', 'public']),
            'location'    => ['nl' => $this->faker->city() . ', België', 'en' => $this->faker->city() . ', Belgium'],
            'client'      => ['nl' => $this->faker->company(), 'en' => $this->faker->company()],
            'year'        => $this->faker->numberBetween(2010, 2026),
            'published'   => true,
            'featured'    => false,
            'order_index' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function draft(): static
    {
        return $this->state(['published' => false]);
    }

    public function featured(): static
    {
        return $this->state(['featured' => true]);
    }
}
