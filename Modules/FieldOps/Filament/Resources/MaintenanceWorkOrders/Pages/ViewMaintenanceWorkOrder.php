<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

class ViewMaintenanceWorkOrder extends ViewRecord
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openEquipment')
                ->label(__('fieldops::resource.work_orders.actions.open_equipment'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(fn (): string => $this->record->maintainable_type === Luminaire::class
                    ? LuminaireResource::getUrl('view', ['record' => $this->record->maintainable_id])
                    : ElectricalBoardResource::getUrl('view', ['record' => $this->record->maintainable_id])),
            Action::make('viewMaintenanceRecord')
                ->label(__('fieldops::resource.work_orders.actions.view_record'))
                ->icon('heroicon-m-document-check')
                ->visible(fn (): bool => $this->record->maintenance_record_id !== null)
                ->url(fn (): string => FoMaintenanceRecordResource::getUrl('view', ['record' => $this->record->maintenance_record_id])),
            Action::make('validateAndClose')
                ->label(__('fieldops::resource.work_orders.actions.validate'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === MaintenanceWorkOrderStatus::AWAITING_VALIDATION)
                ->action(function (): void {
                    app(MaintenanceWorkOrderService::class)->close($this->record, auth()->id());
                    Notification::make()->title(__('fieldops::resource.work_orders.notifications.closed'))->success()->send();
                    $this->refreshFormData(['status', 'validated_at', 'maintenance_record_id']);
                }),
            Action::make('returnForCorrection')
                ->label(__('fieldops::resource.work_orders.actions.return_for_correction'))
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === MaintenanceWorkOrderStatus::AWAITING_VALIDATION)
                ->schema([
                    Textarea::make('return_reason')
                        ->label(__('fieldops::resource.work_orders.fields.return_reason'))
                        ->required()
                        ->maxLength(4000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(MaintenanceWorkOrderService::class)->returnForCorrection(
                        $this->record,
                        auth()->id(),
                        $data['return_reason'],
                    );
                    Notification::make()->title(__('fieldops::resource.work_orders.notifications.returned'))->warning()->send();
                    $this->refreshFormData(['status', 'returned_at', 'returned_by_user_id', 'return_reason']);
                }),
            Action::make('overrideAndClose')
                ->label(__('fieldops::resource.work_orders.actions.override'))
                ->icon('heroicon-m-shield-exclamation')
                ->color('warning')
                ->visible(fn (): bool => ! in_array($this->record->status, [MaintenanceWorkOrderStatus::AWAITING_VALIDATION, MaintenanceWorkOrderStatus::COMPLETED, MaintenanceWorkOrderStatus::CANCELLED], true))
                ->schema([
                    Textarea::make('override_reason')
                        ->label(__('fieldops::resource.work_orders.fields.override_reason'))
                        ->helperText(__('fieldops::resource.work_orders.fields.override_reason_help'))
                        ->required()->rows(4),
                ])
                ->action(function (array $data): void {
                    app(MaintenanceWorkOrderService::class)->close($this->record, auth()->id(), $data['override_reason']);
                    Notification::make()->title(__('fieldops::resource.work_orders.notifications.closed'))->warning()->send();
                    $this->refreshFormData(['status', 'validated_at', 'maintenance_record_id', 'override_reason']);
                }),
            Action::make('cancel')
                ->label(__('fieldops::resource.work_orders.actions.cancel'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! in_array($this->record->status, [MaintenanceWorkOrderStatus::COMPLETED, MaintenanceWorkOrderStatus::CANCELLED], true))
                ->schema([
                    Textarea::make('cancellation_reason')
                        ->label(__('fieldops::resource.work_orders.fields.cancellation_reason'))
                        ->required()->rows(3),
                ])
                ->action(function (array $data): void {
                    app(MaintenanceWorkOrderService::class)->cancel($this->record, auth()->id(), $data['cancellation_reason']);
                    Notification::make()->title(__('fieldops::resource.work_orders.notifications.cancelled'))->danger()->send();
                    $this->refreshFormData(['status', 'cancelled_at', 'cancellation_reason']);
                }),
            EditAction::make()->visible(fn (): bool => FoMaintenanceWorkOrderResource::canEdit($this->record)),
        ];
    }
}
