<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypeResource;

class EditLuminaireType extends EditRecord
{
    protected static string $resource = LuminaireTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
