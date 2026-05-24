<?php

declare(strict_types=1);

namespace Modules\Safety\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Safety\Models\Checklist;

class ChecklistFactory extends Factory
{
    protected $model = Checklist::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->words(3, true),
            'type'      => 'inspection',
            'is_active' => true,
        ];
    }

    public function incident(): static
    {
        return $this->state(['type' => 'incident']);
    }
}
