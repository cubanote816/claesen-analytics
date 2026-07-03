<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypeResource;

class ListStructureTypes extends ListRecords
{
    protected static string $resource = StructureTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
