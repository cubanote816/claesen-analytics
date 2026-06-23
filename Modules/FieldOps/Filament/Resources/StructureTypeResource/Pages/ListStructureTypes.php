<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\StructureTypeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
use Modules\FieldOps\Filament\Resources\StructureTypeResource;

class ListStructureTypes extends ListRecords
{
    use Translatable;

    protected static string $resource = StructureTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
