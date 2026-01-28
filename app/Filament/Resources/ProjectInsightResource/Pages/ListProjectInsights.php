<?php

namespace App\Filament\Resources\ProjectInsightResource\Pages;

use App\Filament\Resources\ProjectInsightResource;
use Filament\Resources\Pages\ListRecords;

class ListProjectInsights extends ListRecords
{
    protected static string $resource = ProjectInsightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
