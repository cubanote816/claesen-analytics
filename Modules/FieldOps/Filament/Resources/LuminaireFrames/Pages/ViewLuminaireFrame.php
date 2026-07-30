<?php

namespace Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;

class ViewLuminaireFrame extends ViewRecord
{
    protected static string $resource = LuminaireFrameResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // ?via_structure=/?via_terrain= carry which structure/terrain the user actually
    // navigated through (LuminaireFrame<->Structure is a real M:N).
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::luminaireFrameAncestors(
            $this->getRecord(),
            request()->integer('via_structure') ?: null,
            request()->integer('via_terrain') ?: null,
        );
    }

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
