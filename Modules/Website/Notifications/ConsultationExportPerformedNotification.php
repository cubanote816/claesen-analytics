<?php

namespace Modules\Website\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Core\Models\User;

/**
 * F2/CLA-465 — "alertas por exportaciones". The premise CLA-465's own
 * original scope note gave ("no existe ninguna función de exportación en
 * el repo todavía") stopped being true once CLA-476 shipped the CSV export
 * of lead PII on app\Filament\Clusters\Website\Resources\
 * ConsultationRequestResource — this is that alert, fired right after the
 * export action's own activity_log entry so both land together.
 */
class ConsultationExportPerformedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $exportedBy,
        private readonly int $recordCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'exported_by_user_id' => $this->exportedBy->id,
            'exported_by_name' => $this->exportedBy->name,
            'record_count' => $this->recordCount,
            'message' => "{$this->exportedBy->name} exported {$this->recordCount} consultation request(s) to CSV.",
        ];
    }
}
