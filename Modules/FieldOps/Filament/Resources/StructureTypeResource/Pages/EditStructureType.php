<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\StructureTypeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
use Modules\FieldOps\Filament\Resources\StructureTypeResource;

class EditStructureType extends EditRecord
{
    use Translatable;

    protected static string $resource = StructureTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false),
        ];
    }
}
