<?php

namespace App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages;

use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsultationRequests extends ListRecords
{
    protected static string $resource = ConsultationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
