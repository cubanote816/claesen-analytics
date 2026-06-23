<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLuminaireFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'luminaire_frame_type_id' => ['sometimes', 'integer', 'exists:fo_luminaire_frame_types,id'],
            // absent → no touch | null → detach all | array → sync
            'structure_ids'           => ['sometimes', 'nullable', 'array'],
            'structure_ids.*'         => ['integer', 'distinct', 'exists:fo_structures,id'],
        ];
    }
}
