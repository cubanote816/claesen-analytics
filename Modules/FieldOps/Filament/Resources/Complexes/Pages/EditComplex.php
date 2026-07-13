<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\Pages;

use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Modules\FieldOps\Filament\Resources\ComplexResource;

class EditComplex extends EditRecord
{
    protected static string $resource = ComplexResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
        ];
    }
}
