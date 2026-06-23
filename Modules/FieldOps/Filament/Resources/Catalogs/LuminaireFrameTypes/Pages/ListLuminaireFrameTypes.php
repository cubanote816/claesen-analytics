<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypeResource;

class ListLuminaireFrameTypes extends ListRecords
{
    protected static string $resource = LuminaireFrameTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
