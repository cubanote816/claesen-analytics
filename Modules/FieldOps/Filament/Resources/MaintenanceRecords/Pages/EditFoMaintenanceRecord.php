<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;

class EditFoMaintenanceRecord extends EditRecord
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
