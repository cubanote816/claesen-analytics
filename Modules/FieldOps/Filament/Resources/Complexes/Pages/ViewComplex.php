<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Filament\Resources\ComplexResource;

class ViewComplex extends ViewRecord
{
    protected static string $resource = ComplexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
