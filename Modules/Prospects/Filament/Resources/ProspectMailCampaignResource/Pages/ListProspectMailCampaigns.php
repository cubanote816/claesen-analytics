<?php

namespace Modules\Prospects\Filament\Resources\ProspectMailCampaignResource\Pages;

use Modules\Prospects\Filament\Resources\ProspectMailCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListProspectMailCampaigns extends ListRecords
{
    protected static string $resource = ProspectMailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
