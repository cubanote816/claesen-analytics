<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Rules\StructureHasFrameCapacity;

class StoreLuminaireFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LuminaireFrame::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'luminaire_frame_type_id' => ['required', 'integer', 'exists:fo_luminaire_frame_types,id'],
            // A brand new frame must resolve to at least one real structure — a
            // frame with 0 structures is an orphan (CLA-278: field app / backoffice
            // parity, same rule the Filament form now enforces on create).
            'structure_ids'           => ['required', 'array', 'min:1'],
            'structure_ids.*'         => ['integer', 'distinct', 'exists:fo_structures,id', new StructureHasFrameCapacity],
        ];
    }
}
