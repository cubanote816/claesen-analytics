<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\FieldOps\Http\Requests\Concerns\ValidatesTenantScopedIds;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Terrain;

class StoreTerrainRequest extends FormRequest
{
    use ValidatesTenantScopedIds;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Terrain::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'complex_id'      => ['required', 'integer', 'exists:fo_complexes,id'],
            'terrain_type_id' => ['required', 'integer', 'exists:fo_terrain_types,id'],
            'name'            => ['nullable', 'array:nl,en,fr,de'],
            'name.nl'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'name.en'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'name.fr'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'name.de'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat'             => ['nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertTenantScopedIds($validator, 'complex_id', Complex::class, $this->input('complex_id'));
        });
    }
}
