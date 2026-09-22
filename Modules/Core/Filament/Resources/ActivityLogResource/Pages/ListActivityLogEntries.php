<?php

namespace Modules\Core\Filament\Resources\ActivityLogResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\ActivityLogResource;

class ListActivityLogEntries extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
