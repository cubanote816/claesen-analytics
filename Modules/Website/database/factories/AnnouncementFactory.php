<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Site;
use Modules\Website\Models\Announcement;

class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'site_id' => fn () => Site::query()->where('key', Site::CLAESEN_KEY)->value('id')
                ?? Site::factory()->create(['key' => Site::CLAESEN_KEY])->id,
            'message' => ['nl' => $this->faker->sentence(), 'en' => $this->faker->sentence()],
            'starts_at' => null,
            'ends_at' => null,
            'status' => Announcement::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => Announcement::STATUS_PUBLISHED]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => Announcement::STATUS_ARCHIVED]);
    }
}
