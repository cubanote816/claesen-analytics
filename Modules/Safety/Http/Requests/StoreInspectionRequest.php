<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

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
            'answers.*.question_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('safety_questions', 'id')
                    ->where('checklist_id', $this->integer('checklist_id')),
            ],
            'answers.*.value'       => ['required', 'in:YES,NO,NA'],
            'answers.*.remark'      => ['nullable', 'string'],
            'photos.*'              => ['image', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $answeredIds = collect($this->input('answers', []))
                ->pluck('question_id')
                ->map(fn($id) => (int) $id)
                ->all();

            foreach ($this->file('photos', []) as $questionId => $file) {
                // Reject non-numeric photo keys
                if (!ctype_digit((string) $questionId)) {
                    $validator->errors()->add(
                        "photos.{$questionId}",
                        "Photo key must be numeric."
                    );
                    continue;
                }
                // Reject orphan photos — 422, not silently ignored
                if (!in_array((int) $questionId, $answeredIds, true)) {
                    $validator->errors()->add(
                        "photos.{$questionId}",
                        "Photo for question {$questionId} has no corresponding answer."
                    );
                }
            }
        });
    }
}
