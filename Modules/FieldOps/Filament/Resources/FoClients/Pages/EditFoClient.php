<?php

namespace Modules\FieldOps\Filament\Resources\FoClients\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\FoClientResource;

class EditFoClient extends EditRecord
{
    protected static string $resource = FoClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
