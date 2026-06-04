<?php

declare(strict_types=1);

namespace Modules\Safety\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Question;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'text_nl'      => $this->faker->sentence(),
            'order'        => $this->faker->numberBetween(1, 20),
            'allow_yes'    => true,
            'allow_no'     => true,
            'allow_na'     => true,
        ];
    }
}
