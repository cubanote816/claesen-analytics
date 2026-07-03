<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\TerrainResource;

class EditTerrain extends EditRecord
{
    protected static string $resource = TerrainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
