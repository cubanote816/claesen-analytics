<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypeResource;

class ListTerrainTypes extends ListRecords
{
    protected static string $resource = TerrainTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
