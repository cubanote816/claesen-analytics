<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Mailing\Filament\Resources\CampaignResource;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;
}
