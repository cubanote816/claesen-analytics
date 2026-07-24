<?php

declare(strict_types=1);

namespace Modules\FieldOps\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\FieldOps\Models\FoMaintenanceRequestAlert;

class MaintenanceRequestAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly FoMaintenanceRequestAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $request = $this->alert->request;
        $typeLabel = $this->alert->alert_type->label();

        return [
            'alert_type' => $this->alert->alert_type->value,
            'maintenance_request_id' => $this->alert->maintenance_request_id,
            'type_label' => $typeLabel,
            'triggered_at' => $this->alert->triggered_at?->toIso8601String(),
            'message' => "{$typeLabel}: request #{$this->alert->maintenance_request_id}"
                .($request ? " ({$request->status->value})" : ''),
        ];
    }
}
