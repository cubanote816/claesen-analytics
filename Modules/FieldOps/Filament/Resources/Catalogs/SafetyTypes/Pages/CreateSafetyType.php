<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypeResource;

class CreateSafetyType extends CreateRecord
{
    protected static string $resource = SafetyTypeResource::class;
}
