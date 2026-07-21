<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;

class CreateFoMaintenanceRecord extends CreateRecord
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    public ?int $returnLuminaireId = null;

    public function mount(): void
    {
        $this->returnLuminaireId = request()->integer('return_luminaire') ?: null;

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        if ($this->returnLuminaireId
            && $this->record->maintainable_type === Luminaire::class
            && (int) $this->record->maintainable_id === $this->returnLuminaireId) {
            return LuminaireResource::getUrl('view', ['record' => $this->returnLuminaireId]);
        }

        return parent::getRedirectUrl();
    }
}
