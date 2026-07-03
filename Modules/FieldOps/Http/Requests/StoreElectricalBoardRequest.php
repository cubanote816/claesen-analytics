<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreElectricalBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
}
