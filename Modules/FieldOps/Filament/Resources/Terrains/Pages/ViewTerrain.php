<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Resources\TerrainResource;

class ViewTerrain extends ViewRecord
{
    protected static string $resource = TerrainResource::class;

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
            Action::make('createStructure')
                ->label(__('fieldops::resource.structures.actions.create'))
                ->button()
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(StructureResource::getUrl('create', [
                    'terrain_ids' => [$this->getRecord()->getKey()],
                ])),
            EditAction::make(),
        ];
    }
}
