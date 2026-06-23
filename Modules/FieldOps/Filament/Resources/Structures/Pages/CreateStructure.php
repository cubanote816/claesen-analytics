<?php

namespace Modules\FieldOps\Filament\Resources\Structures\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\StructureResource;

class CreateStructure extends CreateRecord
{
    protected static string $resource = StructureResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }
}
