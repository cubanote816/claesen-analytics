<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Core\Models\AuthSecurityAlert;

class SecurityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly AuthSecurityAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $threshold = (int) config('core.security_alerts.failed_login_threshold', 10);
        $windowMinutes = (int) config('core.security_alerts.window_minutes', 15);

        return [
            'alert_type' => $this->alert->alert_type,
            'attempt_count' => $this->alert->attempt_count,
            'identifier' => $this->alert->identifier,
            'ip_address' => $this->alert->ip_address,
            'window_started_at' => optional($this->alert->window_started_at)->toDateTimeString(),
            'window_ended_at' => optional($this->alert->window_ended_at)->toDateTimeString(),
            'message' => __('core::access_analytics.security.notifications.message', [
                'count' => $this->alert->attempt_count,
                'threshold' => $threshold,
                'minutes' => $windowMinutes,
                'identifier' => $this->alert->identifier ?? __('core::access_analytics.unknown_user'),
                'ip' => $this->alert->ip_address ?? '—',
            ]),
        ];
    }
}
