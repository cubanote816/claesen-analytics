<?php

namespace Modules\Prospects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Prospects\Enums\ContactDirection;
use Modules\Prospects\Enums\ContactMethod;
use Modules\Prospects\Models\ContactLog;
use Modules\Prospects\Models\Prospect;

class ContactLogFactory extends Factory
{
    protected $model = ContactLog::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'user_id' => null,
            'contacted_at' => now()->subDay(),
            'direction' => fake()->randomElement(ContactDirection::cases()),
            'method' => fake()->randomElement(ContactMethod::cases()),
            'note' => null,
        ];
    }
}
