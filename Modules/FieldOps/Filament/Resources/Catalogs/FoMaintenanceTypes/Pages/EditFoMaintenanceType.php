<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypeResource;

class EditFoMaintenanceType extends EditRecord
{
    protected static string $resource = FoMaintenanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
