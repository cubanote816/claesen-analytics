<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\TerrainTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;
use Modules\FieldOps\Filament\Resources\TerrainTypeResource;

class CreateTerrainType extends CreateRecord
{
    use Translatable;

    protected static string $resource = TerrainTypeResource::class;
}
