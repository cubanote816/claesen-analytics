<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;

class EditMaintenanceWorkOrder extends EditRecord
{
    protected static string $resource = FoMaintenanceWorkOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status === MaintenanceWorkOrderStatus::PLANNED && ! empty($data['assigned_employee_id'])) {
            $data['status'] = MaintenanceWorkOrderStatus::ASSIGNED;
        }

        if ($this->record->status === MaintenanceWorkOrderStatus::ASSIGNED && empty($data['assigned_employee_id'])) {
            $data['status'] = MaintenanceWorkOrderStatus::PLANNED;
        }

        return $data;
    }
}
