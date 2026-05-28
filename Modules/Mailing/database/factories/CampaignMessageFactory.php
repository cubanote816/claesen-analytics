<?php

namespace Modules\Mailing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;

class CampaignMessageFactory extends Factory
{
    protected $model = CampaignMessage::class;

    public function definition(): array
    {
        return [
            'campaign_id'    => Campaign::factory(),
            'prospect_id'    => null,
            'email'          => fake()->safeEmail(),
            'status'         => 'queued',
            'tracking_token' => Str::random(64),
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent', 'sent_at' => now()]);
    }
}
