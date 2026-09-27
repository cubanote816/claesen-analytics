<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\FieldOps\Http\Requests\Concerns\ValidatesTenantScopedIds;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

class UpdateElectricalBoardRequest extends FormRequest
{
    use ValidatesTenantScopedIds;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'electrical_board_type_id'    => ['sometimes', 'integer', 'exists:fo_electrical_board_types,id'],
            'lat'                         => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng'                         => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'location_description'        => ['sometimes', 'nullable', 'array:nl,en,fr,de'],
            'location_description.nl'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_description.en'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_description.fr'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_description.de'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            // absent → no touch | null → detach all | array → sync
            'complex_ids'                 => ['sometimes', 'nullable', 'array'],
            'complex_ids.*'               => ['integer', 'distinct', 'exists:fo_complexes,id'],
            'terrain_ids'                 => ['sometimes', 'nullable', 'array'],
            'terrain_ids.*'               => ['integer', 'distinct', 'exists:fo_terrains,id'],
            'structure_ids'               => ['sometimes', 'nullable', 'array'],
            'structure_ids.*'             => ['integer', 'distinct', 'exists:fo_structures,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // absent and explicit null both resolve to a no-op check (nothing to
            // scope-validate for "no touch" or "detach all") — no separate has()
            // guard needed, unlike the min:1 fields elsewhere in this domain.
            $this->assertTenantScopedIds($validator, 'complex_ids', Complex::class, $this->input('complex_ids'));
            $this->assertTenantScopedIds($validator, 'terrain_ids', Terrain::class, $this->input('terrain_ids'));
            $this->assertTenantScopedIds($validator, 'structure_ids', Structure::class, $this->input('structure_ids'));
        });
    }
}
