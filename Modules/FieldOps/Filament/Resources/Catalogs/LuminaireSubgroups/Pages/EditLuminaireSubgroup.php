<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroups\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroupResource;

class EditLuminaireSubgroup extends EditRecord
{
    protected static string $resource = LuminaireSubgroupResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
