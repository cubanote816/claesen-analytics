<?php

namespace Modules\Performance\Filament\Resources\ProjectInsightResource\Pages;

use Modules\Performance\Filament\Resources\ProjectInsightResource;
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
