<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\TerrainResource;

class ViewComplex extends ViewRecord
{
    protected static string $resource = ComplexResource::class;

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    // See ViewFoClient::getHeading() — profile-header.blade.php already shows the
    // eyebrow+name, so the native heading (which would repeat the same name) is hidden.
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTerrain')
                ->label(__('fieldops::resource.terrains.actions.create'))
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(TerrainResource::getUrl('create', [
                    'complex_id' => $this->record->getKey(),
                ])),
            EditAction::make(),
        ];
    }
}
