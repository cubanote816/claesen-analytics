<?php

namespace Modules\Employee\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'        => 'required|date',
            'hours'       => 'required|numeric|min:0|max:24',
            'project_id'  => 'sometimes|string',
            'description' => 'sometimes|string|max:500',
        ];
    }
}
