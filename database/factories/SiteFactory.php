<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Core\Models\Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'key' => Str::slug($this->faker->unique()->domainWord()),
            'domain' => null,
            'default_locale' => 'nl',
            'locales' => ['nl', 'en', 'fr', 'de'],
            'status' => Site::STATUS_ACTIVE,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => Site::STATUS_SUSPENDED]);
    }
}
