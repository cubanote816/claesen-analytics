<?php

namespace Modules\FieldOps\Filament\Resources\Structures\Pages;

use Illuminate\Support\Arr;
use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\StructureResource;

class CreateStructure extends CreateRecord
{
    protected static string $resource = StructureResource::class;

    public ?array $terrainIds = null;

    public function mount(): void
    {
        parent::mount();

        $terrainIds = request()->input('terrain_ids');
        $this->terrainIds = is_array($terrainIds)
            ? array_values(array_filter(Arr::wrap($terrainIds), fn ($value) => $value !== null && $value !== ''))
            : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record && $this->terrainIds !== null) {
            $this->record->terrains()->syncWithoutDetaching($this->terrainIds);
        }
    }
}
