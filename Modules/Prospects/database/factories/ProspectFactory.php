<?php

namespace Modules\Prospects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;

class ProspectFactory extends Factory
{
    protected $model = Prospect::class;

    public function definition(): array
    {
        return [
            'name'      => fake()->company(),
            'type'      => 'football_club',
            'region_id' => Region::query()->value('id'),
            'is_tester' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Prospect $prospect) {
            ProspectLocation::create([
                'prospect_id'  => $prospect->id,
                'contact_type' => 'headquarters',
                'email'        => fake()->safeEmail(),
            ]);
        });
    }
}
