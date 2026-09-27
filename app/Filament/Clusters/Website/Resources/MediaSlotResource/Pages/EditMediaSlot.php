<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\MediaSlotResource\Pages;

use App\Filament\Clusters\Website\Resources\MediaSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaSlot extends EditRecord
{
    protected static string $resource = MediaSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
