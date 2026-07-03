<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypeResource;

class EditSafetyType extends EditRecord
{
    protected static string $resource = SafetyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
