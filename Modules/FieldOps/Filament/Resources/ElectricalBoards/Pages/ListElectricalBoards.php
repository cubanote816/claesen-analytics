<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class ListElectricalBoards extends ListRecords
{
    protected static string $resource = ElectricalBoardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
