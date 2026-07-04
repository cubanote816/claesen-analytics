<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'street'   => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255'],
            'language' => ['nullable', 'string', 'in:nl,en,fr,de'],
        ];
    }
}
