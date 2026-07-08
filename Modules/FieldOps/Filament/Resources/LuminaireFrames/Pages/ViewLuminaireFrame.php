<?php

namespace Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;

class ViewLuminaireFrame extends ViewRecord
{
    protected static string $resource = LuminaireFrameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
