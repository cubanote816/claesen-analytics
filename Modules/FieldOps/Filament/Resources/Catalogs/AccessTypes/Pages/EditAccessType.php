<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\AccessTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\AccessTypeResource;

class EditAccessType extends EditRecord
{
    protected static string $resource = AccessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
