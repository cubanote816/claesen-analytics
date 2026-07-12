<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class CreateElectricalBoard extends CreateRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $complexId = request()->integer('complex_id');

        if ($complexId) {
            $this->record?->complexes()->syncWithoutDetaching([$complexId]);
        }
    }
}
