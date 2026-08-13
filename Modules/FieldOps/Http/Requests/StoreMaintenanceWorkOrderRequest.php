<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin', 'project_manager']) ?? false;
    }

    public function rules(): array
    {
        return [
            'fo_maintenance_type_id' => ['required', 'integer', 'exists:fo_maintenance_types,id'],
            'assigned_employee_id' => ['nullable', 'string', 'exists:employees,id'],
            'scheduled_for' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:scheduled_for'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'problem_description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'recurrence_unit' => ['nullable', Rule::in(['days', 'weeks', 'months', 'years'])],
            'recurrence_interval' => ['nullable', 'required_with:recurrence_unit', 'integer', 'min:1', 'max:365'],
        ];
    }
}
