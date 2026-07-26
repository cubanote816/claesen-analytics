<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\LuminaireType;

class CreateLuminaire extends CreateRecord
{
    protected static string $resource = LuminaireResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['luminaire_subgroup_id'] = isset($data['luminaire_type_id'])
            ? LuminaireType::find($data['luminaire_type_id'])?->luminaire_subgroup_id
            : null;
        $data['serial_number'] = LuminaireResource::resolveSerialNumber($data['serial_number'] ?? null);
        $data['created_by_user_id'] = auth()->id();
        $data['position_version'] = 1;
        $data['position_source'] = 'backoffice';
        $data['position_verified_at'] = null;
        $data['position_verified_by_user_id'] = null;

        return $data;
    }
}
