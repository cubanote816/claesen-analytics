<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages;

use App\Filament\Clusters\Website\Resources\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Models\Site;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * F3/CLA-469: same rationale as ProjectResource::categoryOptions() —
     * hardcoded to Claesen until Bertels has its own panel/resources (F3/F4).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Site::forPanelOrFail()->id;

        return $data;
    }
}
