<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;

/**
 * @extends Factory<KnxConflictLog>
 */
class KnxConflictLogFactory extends Factory
{
    protected $model = KnxConflictLog::class;

    public function definition(): array
    {
        return [
            'conflict_id' => KnxConflict::factory(),
            'at' => now(),
            'action' => 'reported',
        ];
    }
}
