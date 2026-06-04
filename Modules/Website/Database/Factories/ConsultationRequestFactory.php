<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Models\ConsultationRequest;

class ConsultationRequestFactory extends Factory
{
    protected $model = ConsultationRequest::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->safeEmail(),
            'phone'             => $this->faker->phoneNumber(),
            'company'           => $this->faker->company(),
            'type'              => $this->faker->randomElement(['consultation', 'quote', 'project']),
            'project_type'      => $this->faker->randomElement(['sport', 'industrial', 'public', 'masts', 'other']),
            'message'           => $this->faker->paragraph(),
            'preferred_contact' => 'email',
            'status'            => 'pending',
            'source'            => 'website',
            'priority'          => 'medium',
            'last_activity_at'  => now(),
            'activity_count'    => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'contacted_at' => now()]);
    }
}
