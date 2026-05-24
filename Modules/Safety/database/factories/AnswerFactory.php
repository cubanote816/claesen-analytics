<?php

declare(strict_types=1);

namespace Modules\Safety\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Models\Question;

class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'question_id'   => Question::factory(),
            'status'        => $this->faker->randomElement(['ok', 'nok', 'na']),
            'remark'        => null,
            'photo_path'    => null,
        ];
    }

    public function nok(): static
    {
        return $this->state(['status' => 'nok']);
    }
}
