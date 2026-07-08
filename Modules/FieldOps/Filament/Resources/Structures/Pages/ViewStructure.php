<?php

namespace Modules\FieldOps\Filament\Resources\Structures\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Filament\Resources\StructureResource;

class ViewStructure extends ViewRecord
{
    protected static string $resource = StructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
