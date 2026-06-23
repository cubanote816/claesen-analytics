<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\ComplexResource;

class EditComplex extends EditRecord
{
    protected static string $resource = ComplexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
