<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class CreateElectricalBoard extends CreateRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    public ?int $complexId = null;

    public function mount(): void
    {
        parent::mount();

        $this->complexId = request()->integer('complex_id') ?: null;
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
    }
}
