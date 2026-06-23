<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTerrainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terrain_type_id' => ['sometimes', 'integer', 'exists:fo_terrain_types,id'],
            'name'            => ['sometimes', 'nullable', 'array:nl,en,fr,es'],
            'name.nl'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'name.en'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'name.fr'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'name.es'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat'             => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
