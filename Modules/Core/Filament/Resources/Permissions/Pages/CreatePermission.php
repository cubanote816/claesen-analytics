<?php

namespace Modules\Core\Filament\Resources\Permissions\Pages;

use Modules\Core\Filament\Resources\Permissions\PermissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;
}
