<?php

declare(strict_types=1);

namespace Modules\FieldOps\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;

class WorkOrderOperationalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly FoMaintenanceWorkOrder $workOrder,
        private readonly string $event,
        private readonly string $audience,
        private readonly ?string $reason = null,
    ) {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        $preferences = $notifiable->preferences_data ?? [];
        $databaseEnabled = (bool) data_get(
            $preferences,
            'notifications.fieldopsDatabase',
            data_get($preferences, 'server.notificationsEnabled', true),
        );
        $emailEnabled = (bool) data_get($preferences, 'notifications.fieldopsEmail', true);

        return array_values(array_filter([
            $databaseEnabled ? 'database' : null,
            $emailEnabled && filled($notifiable->email ?? null) ? 'mail' : null,
        ]));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => $this->title(),
            'body' => $this->body(),
            'duration' => 'persistent',
            'icon' => $this->icon(),
            'iconColor' => $this->iconColor(),
            'status' => $this->iconColor(),
            'actions' => [],
            'view' => null,
            'viewData' => [
                'module' => 'fieldops',
                'event' => $this->event,
                'audience' => $this->audience,
                'work_order_id' => $this->workOrder->id,
                'url' => $this->url(),
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title().' — Claesen Outdoor Lighting Platform')
            ->greeting(__('fieldops::resource.work_order_notifications.mail_greeting', ['name' => $notifiable->name]))
            ->line($this->body());

        if ($this->reason) {
            $message->line(__('fieldops::resource.work_order_notifications.reason', ['reason' => $this->reason]));
        }

        return $message
            ->action(__('fieldops::resource.work_order_notifications.open_order'), $this->url())
            ->line(__('fieldops::resource.work_order_notifications.mail_footer'));
    }

    private function title(): string
    {
        return __(
            "fieldops::resource.work_order_notifications.events.{$this->event}.title",
            ['order' => $this->workOrder->id],
        );
    }

    private function body(): string
    {
        return __(
            "fieldops::resource.work_order_notifications.events.{$this->event}.body",
            ['order' => $this->workOrder->id, 'reason' => $this->reason],
        );
    }

    private function url(): string
    {
        if ($this->audience === 'worker') {
            // FieldOps frontend only exposes a task list route (no per-order detail page),
            // so the link must resolve there instead of a nonexistent /work-orders/{id}.
            return rtrim((string) config('fieldops.field_app_url'), '/').'/app/tasks';
        }

        return url("/fo-maintenance-work-orders/{$this->workOrder->id}");
    }

    private function icon(): string
    {
        return match ($this->event) {
            'completed' => 'heroicon-o-check-circle',
            'cancelled', 'reassigned_away' => 'heroicon-o-x-circle',
            'returned' => 'heroicon-o-arrow-uturn-left',
            'submitted' => 'heroicon-o-clipboard-document-check',
            default => 'heroicon-o-wrench-screwdriver',
        };
    }

    private function iconColor(): string
    {
        return match ($this->event) {
            'completed' => 'success',
            'cancelled', 'reassigned_away' => 'danger',
            'returned' => 'warning',
            default => 'info',
        };
    }
}
