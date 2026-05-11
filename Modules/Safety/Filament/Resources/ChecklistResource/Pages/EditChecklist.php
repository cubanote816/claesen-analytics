<?php

namespace Modules\Safety\Filament\Resources\ChecklistResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Safety\Filament\Resources\ChecklistResource;

class EditChecklist extends EditRecord
{
    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
