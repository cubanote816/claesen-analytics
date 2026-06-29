<?php

namespace Modules\Employee\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employeeId');
        return [
            'name'       => 'sometimes|string|max:255',
            'email'      => "sometimes|email|unique:employees,email,{$employeeId},id",
            'mobile'     => 'sometimes|string|max:20',
            'city'       => 'sometimes|string|max:100',
            'start_date' => 'sometimes|date',
        ];
    }
}
