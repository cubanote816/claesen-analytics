<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveClientReportedMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'solution_applied' => ['required', 'string', 'max:1000'],
            'employee_id'      => ['required', 'string', 'exists:employees,id'],
        ];
    }
}
