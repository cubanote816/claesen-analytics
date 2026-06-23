<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\FoClientResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\FoClientResource;

class EditFoClient extends EditRecord
{
    protected static string $resource = FoClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false),
        ];
    }
}
