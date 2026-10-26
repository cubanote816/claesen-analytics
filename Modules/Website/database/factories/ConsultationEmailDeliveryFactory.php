<?php

declare(strict_types=1);

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Site;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Models\ConsultationRequest;

class ConsultationEmailDeliveryFactory extends Factory
{
    protected $model = ConsultationEmailDelivery::class;

    public function definition(): array
    {
        return [
            'site_id' => fn () => Site::query()->where('key', Site::CLAESEN_KEY)->value('id')
                ?? Site::factory()->create(['key' => Site::CLAESEN_KEY])->id,
            'consultation_request_id' => fn () => ConsultationRequest::factory()->create()->id,
            'type' => ConsultationEmailDelivery::TYPE_INTERNAL,
            'recipient' => $this->faker->safeEmail(),
            'status' => ConsultationEmailDelivery::STATUS_QUEUED,
            'attempts' => 0,
        ];
    }

    public function confirmation(): static
    {
        return $this->state(['type' => ConsultationEmailDelivery::TYPE_CONFIRMATION]);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => ConsultationEmailDelivery::STATUS_SENT,
            'attempts' => 1,
            'sent_at' => now(),
        ]);
    }

    public function failed(int $attempts = 1): static
    {
        return $this->state([
            'status' => ConsultationEmailDelivery::STATUS_FAILED,
            'attempts' => $attempts,
            'last_error' => '[RuntimeException] delivery attempt failed.',
        ]);
    }
}
