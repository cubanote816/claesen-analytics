<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\FieldOps\Models\LuminaireType;

class ReplaceLuminaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'luminaire_type_id' => ['required', 'integer', 'exists:fo_luminaire_types,id'],
            'luminaire_subgroup_id' => ['required', 'integer', 'exists:fo_luminaire_subgroups,id'],
            'serial_number' => ['required', 'string', 'max:50', 'unique:fo_luminaires,serial_number'],
            'replacement_reason' => ['required', 'string', 'max:2000'],
            'maintenance_at' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'string', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'root_cause' => ['nullable', 'string', 'max:1000'],
            'solution_applied' => ['nullable', 'string', 'max:1000'],
            'position_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'serial_number.unique' => __('fieldops::resource.luminaires.replacement.serial_taken'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = LuminaireType::find($this->integer('luminaire_type_id'));

            if ($type && (int) $type->luminaire_subgroup_id !== $this->integer('luminaire_subgroup_id')) {
                $validator->errors()->add('luminaire_type_id', 'The luminaire type does not belong to the selected subgroup.');
            }
        });
    }
}
