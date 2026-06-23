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
            'info'               => ['nullable', 'array:nl,en,fr,es'],
            'info.nl'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.en'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.fr'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.es'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'external_safety_id' => ['nullable', 'integer'],
            'external_access_id' => ['nullable', 'integer'],
            'cafca_material_id'  => ['nullable', 'integer'],
            'terrain_ids'        => ['nullable', 'array'],
            'terrain_ids.*'      => ['integer', 'distinct', 'exists:fo_terrains,id'],
        ];
    }
}
