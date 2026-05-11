<?php

namespace Modules\Safety\Filament\Resources\InspectionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Safety\Filament\Resources\InspectionResource;

class ListInspections extends ListRecords
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
