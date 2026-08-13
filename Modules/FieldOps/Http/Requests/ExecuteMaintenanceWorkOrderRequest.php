<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin', 'project_manager']) ?? false;
    }

    public function rules(): array
    {
        return [
            'fo_maintenance_type_id' => ['required', 'integer', 'exists:fo_maintenance_types,id'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'problem_description' => ['nullable', 'string', 'max:2000'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'solution_applied' => ['required', 'string', 'max:4000'],
            'completion_notes' => ['nullable', 'string', 'max:4000'],
            'completion_details' => ['nullable', 'array'],
        ];
    }
}
