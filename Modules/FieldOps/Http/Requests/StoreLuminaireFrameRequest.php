<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLuminaireFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'luminaire_frame_type_id' => ['required', 'integer', 'exists:fo_luminaire_frame_types,id'],
            'structure_ids'           => ['nullable', 'array'],
            'structure_ids.*'         => ['integer', 'distinct', 'exists:fo_structures,id'],
        ];
    }
}
