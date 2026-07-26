<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldOpsMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection' => ['required', Rule::in(['photos', 'documents', 'videos'])],
            'file'       => array_merge(
                ['required', 'file'],
                match ($this->input('collection')) {
                    'documents' => ['mimes:pdf', 'max:20480'],
                    'videos' => ['mimes:mp4,webm,mov,qt', 'max:102400'],
                    default => ['mimes:jpeg,jpg,png,webp', 'max:10240'],
                },
            ),
        ];
    }
}
