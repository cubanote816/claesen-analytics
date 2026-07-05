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

    /**
     * client_id is intentionally absent — the client<->complex link comes from
     * the CAFCA sync (FO-013, ComplexRelationDeliverySyncService) and must never
     * be reassigned through this endpoint. FormRequest::validated() only returns
     * keys with rules defined here, so a client_id sent in the request body is
     * silently dropped before it ever reaches Complex::update().
     */
    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'string', 'max:255'],
            'street'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'zipcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'lat'     => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng'     => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'zoom'    => ['sometimes', 'nullable', 'numeric', 'between:1,22'],
        ];
    }
}
