<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:255'],
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:fo_clients,id'],
            'street'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'zipcode'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'lat'       => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng'       => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'zoom'      => ['sometimes', 'nullable', 'numeric', 'between:1,22'],
        ];
    }
}
