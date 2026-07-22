<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;

class SubmitMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('workOrder');
        $user = $this->user();

        return $order instanceof FoMaintenanceWorkOrder && $user && (
            $user->hasAnyRole(['super_admin', 'admin'])
            || ($user->employee_id && $user->employee_id === $order->assigned_employee_id)
        );
    }

    public function rules(): array
    {
        return [
            'completed_at' => ['nullable', 'date'],
            'completion_details' => ['nullable', 'array'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'solution_applied' => ['required', 'string', 'max:4000'],
            'completion_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
