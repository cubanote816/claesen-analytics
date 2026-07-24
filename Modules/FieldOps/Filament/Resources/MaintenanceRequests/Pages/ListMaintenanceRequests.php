<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceRequests\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRequestResource;
use Modules\FieldOps\Filament\Widgets\MaintenanceRequestLifecycleWidget;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = FoMaintenanceRequestResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenanceRequestLifecycleWidget::class,
        ];
    }
}
