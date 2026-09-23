<?php

namespace Modules\Website\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Website\Models\WebsiteAbuseAlert;

/**
 * F4/CLA-475 — same shape as Modules\Mailing\Notifications\
 * DeliverabilityAlertNotification (database channel only, one flat array
 * of already-computed numbers, no live model reference in the payload).
 */
class WebsiteAbuseAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly WebsiteAbuseAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $site = $this->alert->site;
        $siteLabel = $site?->organization?->name ?? $site?->key ?? "site #{$this->alert->site_id}";

        return [
            'site_id' => $this->alert->site_id,
            'site_label' => $siteLabel,
            'attempt_count' => $this->alert->attempt_count,
            'threshold' => $this->alert->threshold,
            'window_minutes' => $this->alert->window_minutes,
            'message' => "Intake abuse spike on {$siteLabel}: {$this->alert->attempt_count} spam attempt(s) in the last {$this->alert->window_minutes} minute(s) (threshold: {$this->alert->threshold}).",
        ];
    }
}
