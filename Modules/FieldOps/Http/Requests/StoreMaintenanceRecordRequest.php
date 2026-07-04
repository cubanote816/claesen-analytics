<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fo_maintenance_type_id' => ['required', 'integer', 'exists:fo_maintenance_types,id'],
            'employee_id'            => ['nullable', 'string', 'exists:employees,id'],
            'maintenance_at'         => ['required', 'date'],
            'details'                => ['nullable', 'array'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'problem_description'    => ['nullable', 'string', 'max:2000'],
            'root_cause'             => ['nullable', 'string', 'max:1000'],
            'solution_applied'       => ['nullable', 'string', 'max:1000'],
            'is_emergency'           => ['nullable', 'boolean'],
            'problem_reported_at'    => ['nullable', 'date'],
            'problem_solved_at'      => ['nullable', 'date', 'after_or_equal:problem_reported_at'],
            'downtime_hours'         => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
