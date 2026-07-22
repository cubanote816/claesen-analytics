<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

class CreateMaintenanceWorkOrder extends CreateRecord
{
    protected static string $resource = FoMaintenanceWorkOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (empty($data['maintainable_type']) || empty($data['maintainable_id'])) {
            throw ValidationException::withMessages([
                'maintainable_id' => __('fieldops::resource.work_orders.validation.context_required'),
            ]);
        }

        return app(MaintenanceWorkOrderService::class)->create($data, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return match ($this->record->maintainable_type) {
            Luminaire::class => LuminaireResource::getUrl('view', ['record' => $this->record->maintainable_id]),
            ElectricalBoard::class => ElectricalBoardResource::getUrl('view', ['record' => $this->record->maintainable_id]),
            default => FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $this->record]),
        };
    }
}
