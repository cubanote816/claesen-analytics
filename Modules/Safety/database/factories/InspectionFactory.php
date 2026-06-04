<?php

declare(strict_types=1);

namespace Modules\Safety\Database\Factories;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;

class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    public function definition(): array
    {
        return [
            'user_id'            => UserFactory::new(),
            'checklist_id'       => Checklist::factory(),
            'type'               => 'inspection',
            'incident_worker_id' => null,
            'project_id'         => strtoupper($this->faker->bothify('P-####-???')),
            'completed_at'       => now(),
            'pdf_path'           => null,
        ];
    }

    public function inspection(): static
    {
        return $this->state(['type' => 'inspection', 'incident_worker_id' => null]);
    }

    public function incident(): static
    {
        return $this->state(fn () => [
            'type'               => 'incident',
            'incident_worker_id' => UserFactory::new(),
        ]);
    }
}
