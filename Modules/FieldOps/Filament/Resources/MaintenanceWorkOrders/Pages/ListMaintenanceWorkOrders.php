<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;

class ListMaintenanceWorkOrders extends ListRecords
{
    protected static string $resource = FoMaintenanceWorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
