<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Models\ConsultationReminder;
use Modules\Website\Models\ConsultationRequest;

class ConsultationReminderFactory extends Factory
{
    protected $model = ConsultationReminder::class;

    public function definition(): array
    {
        return [
            'consultation_request_id' => ConsultationRequest::factory(),
            'user_id'                 => null,
            'title'                   => $this->faker->sentence(),
            'description'             => $this->faker->paragraph(),
            'remind_at'               => now()->addDay(),
            'status'                  => 'pending',
            'type'                    => 'follow_up',
            'notification_methods'    => ['database'],
        ];
    }

    public function due(): static
    {
        return $this->state(['remind_at' => now()->subMinute()]);
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'completed_at' => now()]);
    }
}
