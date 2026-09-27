<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\MediaSlotResource\Pages;

use App\Filament\Clusters\Website\Resources\MediaSlotResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Models\Site;

class CreateMediaSlot extends CreateRecord
{
    protected static string $resource = MediaSlotResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Site::forPanelOrFail()->id;

        return $data;
    }
}
