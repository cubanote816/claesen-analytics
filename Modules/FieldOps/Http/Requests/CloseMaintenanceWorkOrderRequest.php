<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
