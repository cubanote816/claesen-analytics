<?php

declare(strict_types=1);

namespace Modules\FieldOps\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\FieldOps\Models\FoMaintenanceRequest;

class ClientRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly FoMaintenanceRequest $requestModel,
        private readonly string $event,
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
        $databaseEnabled = (bool) data_get($preferences, 'notifications.fieldopsDatabase', true);
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
            'viewData' => [
                'module' => 'fieldops',
                'request_id' => $this->requestModel->id,
                'event' => $this->event,
                'url' => $this->url($notifiable),
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' — Claesen Outdoor Lighting Platform')
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body())
            ->action('Open request', $this->url($notifiable));
    }

    private function title(): string
    {
        return match ($this->event) {
            'created' => 'New maintenance request',
            'message_received' => 'New client message',
            'resolved' => 'Maintenance request resolved',
            'reopened' => 'Maintenance request reopened',
            default => 'Maintenance request updated',
        };
    }

    private function body(): string
    {
        return $this->requestModel->public_response ?: match ($this->event) {
            'created' => 'A client submitted a new maintenance request.',
            'message_received' => 'A client added a message to a maintenance request.',
            'reopened' => 'A client reopened a maintenance request.',
            default => 'The maintenance request has been updated.',
        };
    }

    private function url(object $notifiable): string
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('client')) {
            return rtrim((string) config('fieldops.client_portal_url'), '/')."/requests/{$this->requestModel->id}";
        }

        return url("/fo-maintenance-requests/{$this->requestModel->id}");
    }
}
