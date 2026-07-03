<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypeResource;

class EditStructureType extends EditRecord
{
    protected static string $resource = StructureTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
