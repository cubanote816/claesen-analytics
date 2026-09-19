<?php

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLuminaireVisionSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fieldops.ai') ?? false;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }
}
