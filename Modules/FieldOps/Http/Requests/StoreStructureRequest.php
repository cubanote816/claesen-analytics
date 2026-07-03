<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'structure_type_id'  => ['required', 'integer', 'exists:fo_structure_types,id'],
            'height'             => ['nullable', 'integer', 'min:0'],
            'lat'                => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                => ['nullable', 'numeric', 'between:-180,180'],
            'info'               => ['nullable', 'array:nl,en,fr,de'],
            'info.nl'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.en'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.fr'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.de'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'access_type_id'     => ['nullable', 'integer', 'exists:fo_access_types,id'],
            'access_active'      => ['nullable', 'boolean'],
            'safety_type_id'     => ['nullable', 'integer', 'exists:fo_safety_types,id'],
            'safety_certified'   => ['nullable', 'boolean'],
            'cafca_material_id'  => ['nullable', 'integer'],
            'terrain_ids'        => ['nullable', 'array'],
            'terrain_ids.*'      => ['integer', 'distinct', 'exists:fo_terrains,id'],
        ];
    }
}
