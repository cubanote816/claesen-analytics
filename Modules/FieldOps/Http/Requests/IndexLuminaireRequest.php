<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the luminaire index (CLA-578). The index itself is tenant-scoped
 * through FieldOpsTenantService, so this only validates the narrowing filters.
 */
class IndexLuminaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'complex_id'          => ['sometimes', 'nullable', 'integer', 'exists:fo_complexes,id'],
            'terrain_id'          => ['sometimes', 'nullable', 'integer', 'exists:fo_terrains,id'],
            'structure_id'        => ['sometimes', 'nullable', 'integer', 'exists:fo_structures,id'],
            'luminaire_frame_id'  => ['sometimes', 'nullable', 'integer', 'exists:fo_luminaire_frames,id'],
            'luminaire_type_id'   => ['sometimes', 'nullable', 'integer', 'exists:fo_luminaire_types,id'],
            'serviced_by_me'      => ['sometimes', 'boolean'],
            'is_current'          => ['sometimes', 'boolean'],
            'per_page'            => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return min(max($this->integer('per_page', 25), 1), 100);
    }
}
