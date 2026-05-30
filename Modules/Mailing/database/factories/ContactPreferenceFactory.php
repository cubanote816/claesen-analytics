<?php

namespace Modules\Mailing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Mailing\Models\ContactPreference;
use Modules\Prospects\Models\Prospect;

class ContactPreferenceFactory extends Factory
{
    protected $model = ContactPreference::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'category'    => $this->faker->randomElement(['offers', 'newsletter', 'events']),
            'subscribed'  => true,
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(['subscribed' => false]);
    }
}
