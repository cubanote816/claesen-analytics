<?php

namespace Modules\Prospects\Filament\Resources\ProspectMailLogResource\Pages;

use Modules\Prospects\Filament\Resources\ProspectMailLogResource;
use Filament\Resources\Pages\ListRecords;

class ListProspectMailLogs extends ListRecords
{
    protected static string $resource = ProspectMailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // List actions
        ];
    }
}
