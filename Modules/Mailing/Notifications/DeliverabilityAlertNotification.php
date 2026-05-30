<?php

namespace Modules\Mailing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Mailing\Models\DeliverabilityAlert;

class DeliverabilityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly DeliverabilityAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $campaign    = $this->alert->campaign;
        $typeLabel   = $this->alert->alert_type->label();
        $ratePercent = round($this->alert->rate * 100, 4);
        $threshold   = round($this->alert->threshold * 100, 4);

        return [
            'alert_type'    => $this->alert->alert_type->value,
            'campaign_id'   => $this->alert->campaign_id,
            'campaign_name' => $campaign?->description ?? "#{$this->alert->campaign_id}",
            'type_label'    => $typeLabel,
            'rate_percent'  => $ratePercent,
            'threshold_pct' => $threshold,
            'sent_count'    => $this->alert->sent_count,
            'event_count'   => $this->alert->event_count,
            'message'       => "{$typeLabel}: {$ratePercent}% (threshold: {$threshold}%) — campaign \"{$campaign?->description}\" ({$this->alert->sent_count} sent)",
        ];
    }
}
