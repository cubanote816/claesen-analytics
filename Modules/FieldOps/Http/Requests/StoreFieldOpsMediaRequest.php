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
            'collection' => ['required', Rule::in(['photos', 'documents'])],
            'file'       => array_merge(
                ['required', 'file'],
                $this->input('collection') === 'documents'
                    ? ['mimes:pdf', 'max:20480']
                    : ['mimes:jpeg,jpg,png,webp', 'max:10240'],
            ),
        ];
    }
}
