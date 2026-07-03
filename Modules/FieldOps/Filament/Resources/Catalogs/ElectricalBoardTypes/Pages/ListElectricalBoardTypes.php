<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\ElectricalBoardTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\ElectricalBoardTypeResource;

class ListElectricalBoardTypes extends ListRecords
{
    protected static string $resource = ElectricalBoardTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
