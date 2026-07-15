<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\FieldOps\Models\Terrain;

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
            // absent → no touch | array → sync (must contain at least one terrain)
            'terrain_ids'        => ['sometimes', 'array', 'min:1'],
            'terrain_ids.*'      => ['integer', 'distinct', 'exists:fo_terrains,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('terrain_ids')) {
                return;
            }

            $terrainIds = collect($this->input('terrain_ids', []))
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->values();

            if ($terrainIds->isEmpty()) {
                return;
            }

            $complexIds = Terrain::query()
                ->whereIn('id', $terrainIds)
                ->pluck('complex_id')
                ->unique()
                ->values();

            if ($complexIds->count() > 1) {
                $validator->errors()->add('terrain_ids', __('fieldops::resource.structures.validation.terrain_same_complex'));
            }
        });
    }
}
