<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;

class CreateFoMaintenanceRecord extends CreateRecord
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
