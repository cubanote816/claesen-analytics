<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:fo_clients,id'],
            'street'  => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'lat'     => ['nullable', 'numeric', 'between:-90,90'],
            'lng'     => ['nullable', 'numeric', 'between:-180,180'],
            'zoom'    => ['nullable', 'numeric', 'between:1,22'],
        ];
    }
}
