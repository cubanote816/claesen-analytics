<?php

namespace App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages;

use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConsultationRequest extends EditRecord
{
    protected static string $resource = ConsultationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->dispatch('refreshActivities');
    }
}
