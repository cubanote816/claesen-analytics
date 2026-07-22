<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Models\ElectricalBoard;

class ViewElectricalBoard extends ViewRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scheduleMaintenance')
                ->label(__('fieldops::resource.electrical_boards.actions.schedule_maintenance'))
                ->icon('heroicon-m-clipboard-document-check')
                ->color('primary')
                ->url(fn (): string => FoMaintenanceWorkOrderResource::getUrl('create', [
                    'maintainable_type' => ElectricalBoard::class,
                    'maintainable_id' => $this->record->id,
                ])),
            EditAction::make(),
        ];
    }
}
