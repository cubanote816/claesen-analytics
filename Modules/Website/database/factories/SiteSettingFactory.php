<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Site;
use Modules\Website\Models\SiteSetting;

class SiteSettingFactory extends Factory
{
    protected $model = SiteSetting::class;

    public function definition(): array
    {
        return [
            'site_id' => fn () => Site::query()->where('key', Site::CLAESEN_KEY)->value('id')
                ?? Site::factory()->create(['key' => Site::CLAESEN_KEY])->id,
            'key' => 'phone',
            'value' => $this->faker->phoneNumber(),
            'type' => SiteSetting::TYPE_TEXT,
        ];
    }

    public function translatable(): static
    {
        return $this->state(fn () => [
            'key' => 'hours',
            'value' => ['nl' => 'Ma-Vr: 9-17u', 'en' => 'Mon-Fri: 9am-5pm'],
            'type' => SiteSetting::TYPE_TRANSLATABLE,
        ]);
    }
}
