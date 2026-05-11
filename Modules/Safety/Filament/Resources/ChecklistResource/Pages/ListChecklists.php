<?php

namespace Modules\Safety\Filament\Resources\ChecklistResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Safety\Filament\Resources\ChecklistResource;

class ListChecklists extends ListRecords
{
    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
