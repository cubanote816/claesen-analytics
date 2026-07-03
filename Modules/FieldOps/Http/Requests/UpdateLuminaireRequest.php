<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\FieldOps\Models\LuminaireType;

class UpdateLuminaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $luminaireId = $this->route('luminaire')?->id;

        return [
            'luminaire_frame_id'    => ['sometimes', 'integer', 'exists:fo_luminaire_frames,id'],
            'luminaire_type_id'     => ['sometimes', 'integer', 'exists:fo_luminaire_types,id'],
            'luminaire_subgroup_id' => ['sometimes', 'integer', 'exists:fo_luminaire_subgroups,id'],
            'serial_number'         => [
                'sometimes', 'string', 'max:50',
                Rule::unique('fo_luminaires', 'serial_number')->ignore($luminaireId),
            ],
            'frame_position'        => ['sometimes', 'nullable', 'integer', 'min:1'],
            'frame_x'               => ['sometimes', 'nullable', 'numeric'],
            'frame_y'               => ['sometimes', 'nullable', 'numeric'],
            'info'                  => ['sometimes', 'nullable', 'array:nl,en,fr,de'],
            'info.nl'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.en'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.fr'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.de'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cafca_material_id'     => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $luminaire  = $this->route('luminaire');

            // Resolve effective type and subgroup — use sent value or fall back to current model
            $typeId     = $this->has('luminaire_type_id')
                ? $this->integer('luminaire_type_id')
                : $luminaire?->luminaire_type_id;
            $subgroupId = $this->has('luminaire_subgroup_id')
                ? $this->integer('luminaire_subgroup_id')
                : $luminaire?->luminaire_subgroup_id;

            if ($typeId && $subgroupId
                && !$v->errors()->has('luminaire_type_id')
                && !$v->errors()->has('luminaire_subgroup_id')
            ) {
                $type = LuminaireType::find($typeId);
                if ($type && (int) $type->luminaire_subgroup_id !== (int) $subgroupId) {
                    $v->errors()->add('luminaire_type_id', 'The luminaire type does not belong to the selected subgroup.');
                }
            }
        });
    }
}
