<?php

namespace Modules\Prospects\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\User;
use Filament\Notifications\Notification;
use Modules\Prospects\Models\SyncHistory;

final class SendMasterSyncFinishedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?int $userId = null,
        protected ?int $historyId = null
    ) {}

    public function handle(): void
    {
        if ($this->historyId) {
            $history = SyncHistory::find($this->historyId);
            if ($history) {
                $logs = $history->logs ?? [];
                $logs[] = [
                    'time' => now()->format('H:i:s'),
                    'message' => 'Sincronización Maestra finalizada globalmente.',
                    'type' => 'success',
                    'icon' => '🏁',
                ];
                
                $history->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'logs' => $logs,
                ]);
            }
        }

        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title('Sincronización Maestra Finalizada')
                    ->body('Todas las federaciones han sido sincronizadas correctamente.')
                    ->success()
                    ->sendToDatabase($user);
            }
        }
    }
}
