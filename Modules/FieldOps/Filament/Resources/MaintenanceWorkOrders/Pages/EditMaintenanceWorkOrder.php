<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

class EditMaintenanceWorkOrder extends EditRecord
{
    protected static string $resource = FoMaintenanceWorkOrderResource::class;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::maintenanceWorkOrderAncestors(
            $this->record->maintainable_type,
            $this->record->maintainable_id,
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = app(MaintenanceWorkOrderService::class);

        // awaiting_validation: this page's fields switch entirely to the review section
        // (root_cause/solution_applied/completion_notes/completion_details) — see
        // FoMaintenanceWorkOrderResource::form(). Any other editable status is still the
        // planning form.
        return $record instanceof FoMaintenanceWorkOrder && $record->status === MaintenanceWorkOrderStatus::AWAITING_VALIDATION
            ? $service->updateReview($record, $data, auth()->id())
            : $service->updatePlanning($record, $data, auth()->id());
    }
}
