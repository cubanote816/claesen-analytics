<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fo_maintenance_type_id' => ['sometimes', 'integer', 'exists:fo_maintenance_types,id'],
            'employee_id'            => ['sometimes', 'nullable', 'string', 'exists:employees,id'],
            'maintenance_at'         => ['sometimes', 'date'],
            'details'                => ['sometimes', 'nullable', 'array'],
            'notes'                  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'problem_description'    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'root_cause'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'solution_applied'       => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_emergency'           => ['sometimes', 'boolean'],
            'problem_reported_at'    => ['sometimes', 'nullable', 'date'],
            'problem_solved_at'      => ['sometimes', 'nullable', 'date', 'after_or_equal:problem_reported_at'],
            'downtime_hours'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
