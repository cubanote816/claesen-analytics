<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Core\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->company()),
            'name' => $this->faker->company(),
            'status' => Organization::STATUS_ACTIVE,
            'retention_policy' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => Organization::STATUS_SUSPENDED]);
    }
}
