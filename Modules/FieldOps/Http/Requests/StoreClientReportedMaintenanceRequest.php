<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Luminaire;

class StoreClientReportedMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintainable_id'     => ['required', 'integer'],
            'maintainable_type'   => ['required', 'string', 'in:' . Luminaire::class . ',' . ElectricalBoard::class],
            'problem_description' => ['required', 'string', 'max:2000'],
            'priority'            => ['nullable', 'string', 'in:high,medium,low'],
            'client_id'           => ['required', 'integer', 'exists:fo_clients,id'],
            'location_details'    => ['nullable', 'string', 'max:255'],
            'contact_person'      => ['nullable', 'string', 'max:100'],
            'contact_phone'       => ['nullable', 'string', 'max:20'],
        ];
    }
}
