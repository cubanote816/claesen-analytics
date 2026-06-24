<?php

namespace Modules\Safety\Filament\Resources\InspectionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Safety\Filament\Resources\InspectionResource;

class ViewInspection extends ViewRecord
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (): ?string => $this->record->pdf_path ? route('safety.admin.pdf', $this->record) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => !empty($this->record->pdf_path)),
        ];
    }
}
