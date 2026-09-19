<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Site;
use Modules\Website\Models\ProjectCategory;

class ProjectCategoryFactory extends Factory
{
    protected $model = ProjectCategory::class;

    public function definition(): array
    {
        return [
            // F1/P3a convention (see Modules\Website\Database\Factories\ProjectFactory):
            // resolves the seeded Claesen site, falls back to creating it only
            // for isolated tests that skip the phase P1 seed migration.
            'site_id' => fn () => Site::query()->where('key', Site::CLAESEN_KEY)->value('id')
                ?? Site::factory()->create(['key' => Site::CLAESEN_KEY])->id,
            'slug' => $this->faker->unique()->slug(2),
            'name' => ['nl' => $this->faker->word(), 'en' => $this->faker->word()],
            'order_index' => 0,
        ];
    }
}
