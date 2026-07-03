<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateElectricalBoardRequest extends FormRequest
{
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
}
