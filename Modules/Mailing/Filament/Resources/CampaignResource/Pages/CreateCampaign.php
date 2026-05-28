<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status']      = CampaignStatus::DRAFT->value;
        $data['created_by']  = auth()->id();
        $data['total_count'] = 0;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
