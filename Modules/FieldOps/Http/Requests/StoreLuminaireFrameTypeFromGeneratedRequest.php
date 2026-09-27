<?php

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLuminaireFrameTypeFromGeneratedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fieldops.ai') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Base64-encoded PNG bytes produced by OpenAiImageGenerationService
            // (already chroma-keyed to transparency) — capped generously above
            // the ~1-2MB a generated catalog illustration actually produces.
            // Real bytes are checked in withValidator() below — base64_decode()
            // alone only rejects invalid base64 characters, not non-image bytes.
            'image_base64' => ['required', 'string', 'max:5000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('image_base64')) {
                return;
            }

            $decoded = base64_decode($this->input('image_base64'), true);

            if ($decoded === false || @getimagesizefromstring($decoded) === false) {
                $validator->errors()->add('image_base64', __('fieldops::resource.catalogs.validation.invalid_generated_image'));
            }
        });
    }
}
