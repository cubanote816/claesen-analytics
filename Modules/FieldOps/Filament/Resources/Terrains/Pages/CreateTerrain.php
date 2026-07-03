<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\TerrainResource;

class CreateTerrain extends CreateRecord
{
    protected static string $resource = TerrainResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }
}
