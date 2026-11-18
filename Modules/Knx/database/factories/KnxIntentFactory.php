<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxIntent;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxIntent>
 */
class KnxIntentFactory extends Factory
{
    protected $model = KnxIntent::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'target_type' => KnxIntent::TARGET_MODULE,
            'target_key' => 'module:factory:1',
            'module_slot' => 1,
            'text' => fake()->sentence(),
            'version' => 1,
            'is_current' => true,
        ];
    }

    /** A module of a real board, so `target_key` matches what the service writes. */
    public function forModule(KnxBoard $board, int $slot): static
    {
        return $this->state(fn (): array => [
            'project_id' => $board->project_id,
            'target_type' => KnxIntent::TARGET_MODULE,
            'target_key' => 'module:'.($board->slug ?? $board->code).':'.$slot,
            'board_id' => $board->getKey(),
            'module_slot' => $slot,
            'device_id' => null,
            'channel' => null,
        ]);
    }
}
