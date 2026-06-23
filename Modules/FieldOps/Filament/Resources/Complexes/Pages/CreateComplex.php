<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\ComplexResource;

class CreateComplex extends CreateRecord
{
    protected static string $resource = ComplexResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }
}
