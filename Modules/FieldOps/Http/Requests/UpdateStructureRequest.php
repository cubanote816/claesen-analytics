<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'structure_type_id'  => ['sometimes', 'integer', 'exists:fo_structure_types,id'],
            'height'             => ['sometimes', 'nullable', 'integer', 'min:0'],
            'lat'                => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng'                => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'info'               => ['sometimes', 'nullable', 'array:nl,en,fr,es'],
            'info.nl'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.en'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.fr'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.es'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'external_safety_id' => ['sometimes', 'nullable', 'integer'],
            'external_access_id' => ['sometimes', 'nullable', 'integer'],
            'cafca_material_id'  => ['sometimes', 'nullable', 'integer'],
            // absent → no touch | null → detach all | array → sync
            'terrain_ids'        => ['sometimes', 'nullable', 'array'],
            'terrain_ids.*'      => ['integer', 'distinct', 'exists:fo_terrains,id'],
        ];
    }
}
