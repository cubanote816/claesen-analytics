<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Models\Site;


class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * F1/P3a (docs/ai/adr-multi-organization.md): the panel has no site picker
     * yet (Bertels gets its own panel in phase P6), so every project created
     * here belongs to the Claesen site.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Site::forPanelOrFail()->id;

        return $data;
    }
}
