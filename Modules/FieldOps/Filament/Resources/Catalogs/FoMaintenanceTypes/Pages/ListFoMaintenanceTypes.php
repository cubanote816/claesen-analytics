<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypeResource;

class ListFoMaintenanceTypes extends ListRecords
{
    protected static string $resource = FoMaintenanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
