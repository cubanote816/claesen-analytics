<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\FieldOps\Models\LuminaireType;

class StoreLuminaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'luminaire_frame_id'    => ['required', 'integer', 'exists:fo_luminaire_frames,id'],
            'luminaire_type_id'     => ['required', 'integer', 'exists:fo_luminaire_types,id'],
            'luminaire_subgroup_id' => ['required', 'integer', 'exists:fo_luminaire_subgroups,id'],
            'serial_number'         => ['required', 'string', 'max:50', 'unique:fo_luminaires,serial_number'],
            'frame_position'        => ['nullable', 'integer', 'min:1'],
            'frame_x'               => ['nullable', 'numeric'],
            'frame_y'               => ['nullable', 'numeric'],
            'info'                  => ['nullable', 'array:nl,en,fr,de'],
            'info.nl'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.en'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.fr'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'info.de'               => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cafca_material_id'     => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $typeId     = $this->integer('luminaire_type_id');
            $subgroupId = $this->integer('luminaire_subgroup_id');

            if ($typeId && $subgroupId
                && !$v->errors()->has('luminaire_type_id')
                && !$v->errors()->has('luminaire_subgroup_id')
            ) {
                $type = LuminaireType::find($typeId);
                if ($type && (int) $type->luminaire_subgroup_id !== $subgroupId) {
                    $v->errors()->add('luminaire_type_id', 'The luminaire type does not belong to the selected subgroup.');
                }
            }
        });
    }
}
