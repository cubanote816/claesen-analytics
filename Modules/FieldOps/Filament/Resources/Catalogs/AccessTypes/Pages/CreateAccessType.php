<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\AccessTypes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\AccessTypeResource;

class CreateAccessType extends CreateRecord
{
    protected static string $resource = AccessTypeResource::class;
}
