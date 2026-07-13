<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Illuminate\Support\Arr;
use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class CreateElectricalBoard extends CreateRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    public ?int $complexId = null;
    public ?array $structureIds = null;
    public ?array $terrainIds = null;

    public function mount(): void
    {
        parent::mount();

        $this->complexId = request()->integer('complex_id') ?: null;
        $structureIds = request()->input('structure_ids');
        $this->structureIds = is_array($structureIds)
            ? array_values(array_filter($structureIds, fn ($value) => $value !== null && $value !== ''))
            : null;
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
        if ($this->complexId) {
            $this->record?->complexes()->syncWithoutDetaching([$this->complexId]);
        }

        if ($this->structureIds !== null) {
            $this->record?->structures()->syncWithoutDetaching($this->structureIds);
        }

        if ($this->terrainIds !== null) {
            $this->record?->terrains()->syncWithoutDetaching($this->terrainIds);
        }
    }
}
