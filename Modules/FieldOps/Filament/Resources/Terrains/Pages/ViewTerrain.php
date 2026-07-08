<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Filament\Resources\TerrainResource;

class ViewTerrain extends ViewRecord
{
    protected static string $resource = TerrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
