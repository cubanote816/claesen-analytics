<?php

namespace Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;

class CreateLuminaireFrame extends CreateRecord
{
    protected static string $resource = LuminaireFrameResource::class;

    public ?array $structureIds = null;

    public function mount(): void
    {
        parent::mount();

        $structureIds = request()->input('structure_ids');
        $this->structureIds = is_array($structureIds)
            ? array_values(array_filter($structureIds, fn ($value) => $value !== null && $value !== ''))
            : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record && $this->structureIds !== null) {
            $this->record->structures()->syncWithoutDetaching($this->structureIds);
        }
    }
}
