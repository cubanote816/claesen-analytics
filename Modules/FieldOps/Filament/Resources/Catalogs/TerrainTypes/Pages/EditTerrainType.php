<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypeResource;

class EditTerrainType extends EditRecord
{
    protected static string $resource = TerrainTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
