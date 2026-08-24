<?php

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLuminaireFrameTypeFromGeneratedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Base64-encoded PNG bytes produced by GeminiImageGenerationService
            // (already chroma-keyed to transparency) — capped generously above
            // the ~1-2MB a generated catalog illustration actually produces.
            'image_base64' => ['required', 'string', 'max:5000000'],
        ];
    }
}
