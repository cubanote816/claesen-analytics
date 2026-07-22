<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

class EditMaintenanceWorkOrder extends EditRecord
{
    protected static string $resource = FoMaintenanceWorkOrderResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(MaintenanceWorkOrderService::class)->updatePlanning($record, $data, auth()->id());
    }
}
