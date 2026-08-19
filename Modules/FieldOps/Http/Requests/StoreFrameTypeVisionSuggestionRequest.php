<?php

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFrameTypeVisionSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }
}
