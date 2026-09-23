<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;

/**
 * Shared filter validation for the work-order listing, reporting and export
 * endpoints (CLA-578). Authorization is intentionally left to the endpoints
 * themselves: every one of them narrows the query to what the caller may
 * already read through /maintenance-work-orders/history.
 */
class WorkOrderQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from'                => ['sometimes', 'nullable', 'date'],
            'to'                  => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'client_id'           => ['sometimes', 'nullable', 'integer', 'exists:fo_clients,id'],
            'complex_id'          => ['sometimes', 'nullable', 'integer', 'exists:fo_complexes,id'],
            'employee_id'         => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'maintenance_type_id' => ['sometimes', 'nullable', 'integer', 'exists:fo_maintenance_types,id'],
            'status'              => ['sometimes', 'array'],
            'status.*'            => [Rule::enum(MaintenanceWorkOrderStatus::class)],
            'per_page'            => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Filters only — never the pagination knob, which is read separately.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->except('per_page'),
            static fn ($value): bool => $value !== null && $value !== [],
        );
    }

    public function perPage(): int
    {
        return min(max($this->integer('per_page', 25), 1), 100);
    }
}
