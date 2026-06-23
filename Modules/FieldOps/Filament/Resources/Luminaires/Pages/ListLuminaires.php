<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\LuminaireResource;

class ListLuminaires extends ListRecords
{
    protected static string $resource = LuminaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
