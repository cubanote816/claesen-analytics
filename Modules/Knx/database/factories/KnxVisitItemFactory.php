<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxVisit;
use Modules\Knx\Models\KnxVisitItem;

/**
 * @extends Factory<KnxVisitItem>
 */
class KnxVisitItemFactory extends Factory
{
    protected $model = KnxVisitItem::class;

    public function definition(): array
    {
        return [
            'visit_id' => KnxVisit::factory(),
            'kind' => KnxVisit::ITEM_PENDING,
            'label' => $this->faker->sentence(3),
            'position' => 0,
        ];
    }
}
