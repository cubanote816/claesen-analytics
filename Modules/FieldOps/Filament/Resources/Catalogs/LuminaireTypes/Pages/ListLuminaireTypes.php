<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypeResource;

class ListLuminaireTypes extends ListRecords
{
    protected static string $resource = LuminaireTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
