<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class EditElectricalBoard extends EditRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
