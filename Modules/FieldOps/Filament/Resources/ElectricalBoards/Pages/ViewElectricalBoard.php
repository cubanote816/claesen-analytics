<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class ViewElectricalBoard extends ViewRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
