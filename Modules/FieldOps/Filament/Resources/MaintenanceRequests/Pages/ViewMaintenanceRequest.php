<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenanceRequests\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRequestResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Services\MaintenanceRequestService;

class ViewMaintenanceRequest extends ViewRecord
{
    protected static string $resource = FoMaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('respond')
                ->label('Respond / add note')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->schema([
                    Select::make('status')
                        ->options(FoMaintenanceRequestResource::statusOptions())
                        ->default(fn () => $this->record->status->value),
                    Textarea::make('public_response')->label('Public response')->rows(4),
                    Textarea::make('internal_notes')->label('Internal note')->rows(4),
                ])
                ->action(function (array $data): void {
                    app(MaintenanceRequestService::class)->respond($this->record, auth()->user(), $data);
                    Notification::make()->title('Service request updated')->success()->send();
                    $this->redirect(FoMaintenanceRequestResource::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('convert')
                ->label('Create work order')
                ->icon('heroicon-m-clipboard-document-check')
                ->visible(fn (): bool => $this->record->work_order_id === null)
                ->requiresConfirmation()
                ->action(function (): void {
                    $order = app(MaintenanceRequestService::class)->convertToWorkOrder(
                        $this->record,
                        (int) auth()->id(),
                    );
                    Notification::make()->title('Work order created')->success()->send();
                    // Land directly on the edit form so the backoffice user can finish
                    // assignment, date, priority and instructions without an extra click.
                    $this->redirect(FoMaintenanceWorkOrderResource::getUrl('edit', ['record' => $order]));
                }),
            Action::make('openWorkOrder')
                ->label('Open work order')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->visible(fn (): bool => $this->record->work_order_id !== null)
                ->url(fn (): string => FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $this->record->work_order_id])),
            Action::make('cancel')
                ->label('Cancel request')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [
                    MaintenanceRequestStatus::RECEIVED,
                    MaintenanceRequestStatus::IN_REVIEW,
                    MaintenanceRequestStatus::REOPENED,
                ], true))
                ->schema([
                    Textarea::make('cancellation_reason')
                        ->label('Cancellation reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    app(MaintenanceRequestService::class)->cancel($this->record, auth()->user(), $data['cancellation_reason']);
                    Notification::make()->title('Service request cancelled')->danger()->send();
                    $this->redirect(FoMaintenanceRequestResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
