<?php

namespace Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;

class EditLuminaireFrame extends EditRecord
{
    protected static string $resource = LuminaireFrameResource::class;

    // See ViewLuminaireFrame::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::luminaireFrameAncestors(
            $this->getRecord(),
            request()->integer('via_structure') ?: null,
            request()->integer('via_terrain') ?: null,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
