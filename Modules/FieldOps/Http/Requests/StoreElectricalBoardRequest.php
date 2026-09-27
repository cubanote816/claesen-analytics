<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\FieldOps\Http\Requests\Concerns\ValidatesTenantScopedIds;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

class StoreElectricalBoardRequest extends FormRequest
{
    use ValidatesTenantScopedIds;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ElectricalBoard::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'electrical_board_type_id'    => ['required', 'integer', 'exists:fo_electrical_board_types,id'],
            'lat'                         => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                         => ['nullable', 'numeric', 'between:-180,180'],
            'location_description'        => ['nullable', 'array:nl,en,fr,de'],
            'location_description.nl'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_description.en'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_description.fr'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location_description.de'     => ['sometimes', 'nullable', 'string', 'max:1000'],
            'complex_ids'                 => ['nullable', 'array'],
            'complex_ids.*'               => ['integer', 'distinct', 'exists:fo_complexes,id'],
            'terrain_ids'                 => ['nullable', 'array'],
            'terrain_ids.*'               => ['integer', 'distinct', 'exists:fo_terrains,id'],
            'structure_ids'               => ['nullable', 'array'],
            'structure_ids.*'             => ['integer', 'distinct', 'exists:fo_structures,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertTenantScopedIds($validator, 'complex_ids', Complex::class, $this->input('complex_ids'));
            $this->assertTenantScopedIds($validator, 'terrain_ids', Terrain::class, $this->input('terrain_ids'));
            $this->assertTenantScopedIds($validator, 'structure_ids', Structure::class, $this->input('structure_ids'));
        });
    }
}
