<?php

namespace App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages;

use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Models\Site;

class CreateConsultationRequest extends CreateRecord
{
    protected static string $resource = ConsultationRequestResource::class;

    /**
     * F1/P3a (docs/ai/adr-multi-organization.md): the panel has no site picker
     * yet (phase P6), so every lead created here belongs to the Claesen site.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Site::forPanelOrFail()->id;

        return $data;
    }
}
