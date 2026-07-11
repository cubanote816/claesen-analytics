<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'in:nl,en,fr,es'],
            'theme'    => ['required', 'string', 'in:dark,light'],
        ];
    }

    public function messages(): array
    {
        return [
            'language.in' => 'El idioma seleccionado no es válido. Solo se permiten: nl, en, fr, es.',
            'theme.in'    => 'El tema seleccionado no es válido. Solo se permiten: dark, light.',
        ];
    }
}
