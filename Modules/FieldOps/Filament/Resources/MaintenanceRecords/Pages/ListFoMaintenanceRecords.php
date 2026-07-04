<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;

class ListFoMaintenanceRecords extends ListRecords
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
