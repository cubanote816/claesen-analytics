<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\LuminaireResource;

class CreateLuminaire extends CreateRecord
{
    protected static string $resource = LuminaireResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        $data['position_version'] = 1;
        $data['position_source'] = 'backoffice';
        $data['position_verified_at'] = null;
        $data['position_verified_by_user_id'] = null;

        return $data;
    }
}
