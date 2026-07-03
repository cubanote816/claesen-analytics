<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroups\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroupResource;

class ListLuminaireSubgroups extends ListRecords
{
    protected static string $resource = LuminaireSubgroupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
