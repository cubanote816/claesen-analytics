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
            'info'               => ['sometimes', 'nullable', 'array:nl,en,fr,de'],
            'info.nl'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.en'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.fr'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.de'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'access_type_id'     => ['sometimes', 'nullable', 'integer', 'exists:fo_access_types,id'],
            'access_active'      => ['sometimes', 'nullable', 'boolean'],
            'safety_type_id'     => ['sometimes', 'nullable', 'integer', 'exists:fo_safety_types,id'],
            'safety_certified'   => ['sometimes', 'nullable', 'boolean'],
            'cafca_material_id'  => ['sometimes', 'nullable', 'integer'],
            // absent → no touch | null → detach all | array → sync
            'terrain_ids'        => ['sometimes', 'nullable', 'array'],
            'terrain_ids.*'      => ['integer', 'distinct', 'exists:fo_terrains,id'],
        ];
    }
}
