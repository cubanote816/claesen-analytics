<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFoClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'street'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'in:nl,en,fr,de'],
        ];
    }
}
