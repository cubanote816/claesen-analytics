<?php

namespace Modules\Mailing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Mailing\Enums\SuppressionReason;
use Modules\Mailing\Models\SuppressionEntry;

class SuppressionEntryFactory extends Factory
{
    protected $model = SuppressionEntry::class;

    public function definition(): array
    {
        return [
            'email'         => fake()->unique()->safeEmail(),
            'reason'        => SuppressionReason::MANUAL,
            'suppressed_at' => now(),
        ];
    }

    public function hardBounce(): static
    {
        return $this->state(['reason' => SuppressionReason::HARD_BOUNCE]);
    }

    public function spamComplaint(): static
    {
        return $this->state(['reason' => SuppressionReason::SPAM_COMPLAINT]);
    }
}
