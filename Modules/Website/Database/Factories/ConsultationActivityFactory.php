<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Models\ConsultationActivity;
use Modules\Website\Models\ConsultationRequest;

class ConsultationActivityFactory extends Factory
{
    protected $model = ConsultationActivity::class;

    public function definition(): array
    {
        return [
            'consultation_request_id' => ConsultationRequest::factory(),
            'user_id'                 => null,
            'type'                    => $this->faker->randomElement(['status_change', 'comment', 'created', 'priority_change']),
            'title'                   => $this->faker->sentence(),
            'description'             => $this->faker->paragraph(),
            'data'                    => [],
            'activity_at'             => now(),
        ];
    }
}
