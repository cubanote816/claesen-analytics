<?php

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireFactory extends Factory
{
    protected $model = Luminaire::class;

    public function definition(): array
    {
        $subgroup = LuminaireSubgroup::factory()->create();
        $type     = LuminaireType::factory()->create(['luminaire_subgroup_id' => $subgroup->id]);

        return [
            'luminaire_frame_id'    => LuminaireFrame::factory(),
            'luminaire_type_id'     => $type->id,
            'luminaire_subgroup_id' => $subgroup->id,
            'serial_number'         => strtoupper($this->faker->unique()->bothify('SN-####-????')),
        ];
    }
}
