<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Models\ConsultationNotification;
use Modules\Website\Models\ConsultationRequest;

class ConsultationNotificationFactory extends Factory
{
    protected $model = ConsultationNotification::class;

    public function definition(): array
    {
        return [
            'consultation_request_id' => ConsultationRequest::factory(),
            'user_id'                 => UserFactory::new(),
            'type'                    => $this->faker->randomElement(['assignment', 'status_change', 'due_date']),
            'title'                   => $this->faker->sentence(),
            'message'                 => $this->faker->paragraph(),
            'data'                    => [],
            'priority'                => 'medium',
            'is_read'                 => false,
        ];
    }

    public function read(): static
    {
        return $this->state(['is_read' => true, 'read_at' => now()]);
    }
}
