<?php

namespace Modules\Prospects\Jobs;

use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\User;
use Modules\Prospects\Models\SyncHistory;

/**
 * Chain catch handler for the master sync pipeline.
 *
 * When any ExecuteSyncJob in the Bus::chain fails, Laravel invokes this job
 * via ->catch(). It must flip the master SyncHistory from 'running' to
 * 'failed', append the error to the logs, and set finished_at — so the
 * dashboard never shows a dead sync as still running.
 *
 * @see MasterSyncJob
 */
final class MarkMasterSyncFailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?int $userId,
        protected int $historyId,
        protected string $errorMessage = '',
    ) {}

    public function handle(): void
    {
        $history = SyncHistory::find($this->historyId);
        if (! $history || ! in_array($history->status, ['pending', 'running'], true)) {
            return;
        }

        $logs = $history->logs ?? [];
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'message' => "Master sync chain failed: {$this->errorMessage}",
            'type' => 'error',
            'icon' => '❌',
        ];

        $history->update([
            'status' => 'failed',
            'finished_at' => now(),
            'logs' => $logs,
        ]);

        if ($this->userId && ($user = User::find($this->userId))) {
            Notification::make()
                ->title('Sincronización Maestra Fallida')
                ->body('La sincronización maestra ha fallado. La cadena fue detenida.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
