<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;

class ViewLuminaire extends ViewRecord
{
    protected static string $resource = LuminaireResource::class;

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
            Action::make('openFrame')
                ->label(__('fieldops::resource.luminaires.actions.open_in_frame'))
                ->icon('heroicon-m-map')
                ->color('gray')
                ->visible(fn (): bool => $this->record->luminaire_frame_id !== null)
                ->url(fn (): string => LuminaireFrameResource::getUrl('view', [
                    'record' => $this->record->luminaire_frame_id,
                    'layout' => 'technical',
                    'luminaire' => $this->record->id,
                ])),
            Action::make('addMaintenance')
                ->label(__('fieldops::resource.luminaires.actions.add_maintenance'))
                ->icon('heroicon-m-wrench-screwdriver')
                ->color('primary')
                ->url(fn (): string => FoMaintenanceRecordResource::getUrl('create', [
                    'maintainable_type' => Luminaire::class,
                    'maintainable_id' => $this->record->id,
                    'return_luminaire' => $this->record->id,
                ])),
            EditAction::make(),
        ];
    }
}
