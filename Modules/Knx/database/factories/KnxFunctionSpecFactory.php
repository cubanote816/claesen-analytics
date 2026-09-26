<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxProject;

/**
 * @extends Factory<KnxFunctionSpec>
 */
class KnxFunctionSpecFactory extends Factory
{
    protected $model = KnxFunctionSpec::class;

    public function definition(): array
    {
        return [
            'project_id' => KnxProject::factory(),
            'zone_id' => null,
            'name' => 'Verlichting '.fake()->word(),
            'objective' => 'Bij aanwezigheid schakelt de verlichting in en dimt ze naar 70%.',
            'triggers' => ['Aanwezigheidsdetector actief'],
            'conditions' => ['Enkel tijdens kantooruren'],
            'manual_controls' => ['Drukknop bij de deur'],
            'automations' => ['Dimmen naar 70% na 5 minuten'],
            'acceptance_criteria' => ['Licht gaat aan binnen 2 seconden'],
            'status' => 'draft',
            'version' => 1,
        ];
    }
}
