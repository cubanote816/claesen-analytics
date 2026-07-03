<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypeResource;

class EditLuminaireFrameType extends EditRecord
{
    protected static string $resource = LuminaireFrameTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
