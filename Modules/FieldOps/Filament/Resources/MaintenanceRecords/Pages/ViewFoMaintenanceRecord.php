<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;

class ViewFoMaintenanceRecord extends ViewRecord
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openLuminaire')
                ->label(__('fieldops::resource.luminaires.actions.back_to_luminaire'))
                ->icon('heroicon-m-light-bulb')
                ->color('gray')
                ->visible(fn (): bool => $this->record->maintainable_type === Luminaire::class)
                ->url(fn (): string => LuminaireResource::getUrl('view', ['record' => $this->record->maintainable_id])),
            EditAction::make(),
        ];
    }
}
