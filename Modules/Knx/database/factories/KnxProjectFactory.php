<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxClient;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxProject>
 *
 * `organization_id` is left out on purpose: BelongsToKnxTenant fills it from
 * KnxTenant on create, which is the same path production writes take.
 */
class KnxProjectFactory extends Factory
{
    protected $model = KnxProject::class;

    public function definition(): array
    {
        return [
            'client_id' => KnxClient::factory(),
            'code' => strtoupper(fake()->unique()->bothify('C####')),
            'name' => fake()->words(2, true).' · '.fake()->word(),
            'city' => fake()->city(),
            'devices_planned' => fake()->numberBetween(4, 60),
            'devices_done' => 0,
            'photos' => 0,
            'status' => 'planned',
            'deadline' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
        ];
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function forClient(KnxClient $client): static
    {
        return $this->state(fn (): array => ['client_id' => $client->id]);
    }
}
