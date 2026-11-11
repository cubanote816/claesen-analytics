<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Filament\Widgets\CampaignMetricsWidget;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getFooterWidgets(): array
    {
        return [CampaignMetricsWidget::class];
    }

    public function getWidgetData(): array
    {
        return array_merge(parent::getWidgetData(), [
            'campaignId' => $this->record->id,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label(__('mailing::resource.actions.submit_review'))
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (): bool =>
                    $this->record->canTransitionTo(CampaignStatus::REVIEW)
                    && (auth()->user()?->can('submit', $this->record) ?? false)
                )
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->transitionTo(CampaignStatus::REVIEW);
                    $this->refreshFormData(['status']);
                    Notification::make()
                        ->title(__('mailing::resource.notifications.submitted_review'))
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label(__('mailing::resource.actions.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool =>
                    $this->record->canTransitionTo(CampaignStatus::APPROVED)
                    && (auth()->user()?->can('approve', $this->record) ?? false)
                )
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $this->record->transitionTo(CampaignStatus::APPROVED, auth()->id());
                    } catch (\DomainException $e) {
                        // Approval re-checks the template (CLA-669): show why instead of a 500.
                        Notification::make()
                            ->title(__('mailing::resource.notifications.approve_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                    Notification::make()
                        ->title(__('mailing::resource.notifications.approved'))
                        ->success()
                        ->send();
                }),

            Action::make('send_now')
                ->label(__('mailing::resource.actions.send_now'))
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                // canBeSentNow() guards against Gate::before bypassing the policy for super_admin.
                ->visible(fn (): bool =>
                    $this->record->canBeSentNow()
                    && (auth()->user()?->can('sendNow', $this->record) ?? false)
                )
                ->requiresConfirmation()
                ->modalDescription(__('mailing::resource.actions.send_now_confirm'))
                ->action(function (): void {
                    try {
                        $queued = $this->record->dispatchImmediateSend();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title(__('mailing::resource.notifications.send_now_failed'))
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! $queued) {
                        Notification::make()
                            ->title(__('mailing::resource.notifications.send_now_unavailable'))
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->refreshFormData(['status']);
                    Notification::make()
                        ->title(__('mailing::resource.notifications.sent_now'))
                        ->success()
                        ->send();
                }),

            Action::make('cancel')
                ->label(__('mailing::resource.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool =>
                    $this->record->canTransitionTo(CampaignStatus::CANCELLED)
                    && (auth()->user()?->can('cancel', $this->record) ?? false)
                )
                ->requiresConfirmation()
                ->modalDescription(__('mailing::resource.actions.cancel_confirm'))
                ->action(function (): void {
                    $this->record->transitionTo(CampaignStatus::CANCELLED);
                    $this->refreshFormData(['status']);
                    Notification::make()
                        ->title(__('mailing::resource.notifications.cancelled'))
                        ->warning()
                        ->send();
                }),
        ];
    }
}
