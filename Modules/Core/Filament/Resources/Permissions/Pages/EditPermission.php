<?php

namespace Modules\Core\Filament\Resources\Permissions\Pages;

use Modules\Core\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
