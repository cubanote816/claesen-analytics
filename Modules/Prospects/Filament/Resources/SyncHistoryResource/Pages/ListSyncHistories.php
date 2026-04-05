<?php

namespace Modules\Prospects\Filament\Resources\SyncHistoryResource\Pages;

use Modules\Prospects\Filament\Resources\SyncHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListSyncHistories extends ListRecords
{
    protected static string $resource = SyncHistoryResource::class;

    protected static ?string $title = 'Synchronisatie Beheer';

    protected function getHeaderActions(): array
    {
        return [
            // Header actions are already defined in the resource itself, 
            // but we can add more page-specific ones here if needed.
        ];
    }
}
