<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypeResource;

class ListSafetyTypes extends ListRecords
{
    protected static string $resource = SafetyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
