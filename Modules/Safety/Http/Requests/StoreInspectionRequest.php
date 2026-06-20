<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('answers') && is_string($this->answers)) {
            $this->merge(['answers' => json_decode($this->answers, true)]);
        }
    }

    public function rules(): array
    {
        return [
            'idempotency_key'       => ['nullable', 'string', 'uuid'],
            'checklist_id'          => ['required', 'exists:safety_checklists,id'],
            'type'                  => ['required', 'in:inspection,incident'],
            'project_id'            => ['required', 'string'],
            'present_workers'       => ['required_if:type,inspection', 'array'],
            'present_workers.*'     => ['exists:employees,id'],
            'incident_worker_id'    => ['required_if:type,incident', 'exists:employees,id'],
            'answers'               => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:safety_questions,id'],
            'answers.*.value'       => ['required', 'in:YES,NO,NA'],
            'answers.*.remark'      => ['nullable', 'string'],
            'photos.*'              => ['image', 'max:5120'],
        ];
    }
}
