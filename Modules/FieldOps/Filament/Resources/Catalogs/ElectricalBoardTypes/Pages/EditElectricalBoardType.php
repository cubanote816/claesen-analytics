<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\ElectricalBoardTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\ElectricalBoardTypeResource;

class EditElectricalBoardType extends EditRecord
{
    protected static string $resource = ElectricalBoardTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
