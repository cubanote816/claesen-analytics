<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\FieldOps\Rules\StructureHasFrameCapacity;

class UpdateLuminaireFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Update intentionally does NOT require at least one structure — frames
        // that were already orphaned before this rule existed must stay editable
        // (same "required only on create" precedent as LuminaireResource's
        // complex/terrain/structure cascade).
        return [
            'luminaire_frame_type_id' => ['sometimes', 'integer', 'exists:fo_luminaire_frame_types,id'],
            // absent → no touch | null → detach all | array → sync
            'structure_ids'           => ['sometimes', 'nullable', 'array'],
            'structure_ids.*'         => [
                'integer',
                'distinct',
                'exists:fo_structures,id',
                new StructureHasFrameCapacity($this->route('frame')?->id),
            ],
        ];
    }
}
