<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\StructureTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;
use Modules\FieldOps\Filament\Resources\StructureTypeResource;

class CreateStructureType extends CreateRecord
{
    use Translatable;

    protected static string $resource = StructureTypeResource::class;
}
