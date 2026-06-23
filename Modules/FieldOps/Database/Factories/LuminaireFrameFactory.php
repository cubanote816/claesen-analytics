<?php

namespace Modules\FieldOps\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;

class LuminaireFrameFactory extends Factory
{
    protected $model = LuminaireFrame::class;

    public function definition(): array
    {
        return [
            'luminaire_frame_type_id' => LuminaireFrameType::factory(),
        ];
    }
}
