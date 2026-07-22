<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenancePlans\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\FoMaintenancePlanResource;

class ListMaintenancePlans extends ListRecords
{
    protected static string $resource = FoMaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
