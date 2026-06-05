<?php

namespace Modules\Prospects\Traits;

use Modules\Prospects\Models\SyncHistory;
use Carbon\Carbon;

trait LogsSyncEvents
{
    protected ?SyncHistory $syncHistory = null;
    protected array $accumulatedLogs = [];

    public function startSyncLog(?int $userId = null, ?int $historyId = null)
    {
        if ($historyId) {
            $this->syncHistory = SyncHistory::find($historyId);
        }

        if (!$this->syncHistory) {
            $this->syncHistory = SyncHistory::create([
                'command' => $this->getName(),
                'status' => 'running',
                'started_at' => Carbon::now(),
                'user_id' => $userId,
                'logs' => [],
            ]);
        } else {
            // Load existing logs so subsequent chained commands append rather than overwrite
            $this->accumulatedLogs = $this->syncHistory->fresh()->logs ?? [];
            $this->syncHistory->update([
                'status' => 'running',
                'started_at' => $this->syncHistory->started_at ?? Carbon::now(),
            ]);
        }

        $this->logSyncEvent('Starting synchronization...', 'info', '🚀');
    }

    public function logSyncEvent(string $message, string $type = 'info', string $icon = '✅')
    {
        $logEntry = [
            'time' => Carbon::now()->format('H:i:s'),
            'message' => $message,
            'type' => $type,
            'icon' => $icon,
        ];

        $this->accumulatedLogs[] = $logEntry;

        if (count($this->accumulatedLogs) % 5 === 0) {
            $this->syncHistory?->update([
                'logs' => $this->accumulatedLogs,
            ]);
        }
    }

    public function finishSyncLog(int $recordsCount)
    {
        $this->logSyncEvent("Synchronization completed. Processed {$recordsCount} records.", 'success', '🏁');

        $this->syncHistory?->update([
            'status' => 'completed',
            'records_count' => $recordsCount,
            'logs' => $this->accumulatedLogs,
            'finished_at' => Carbon::now(),
        ]);
    }

    public function failSyncLog(string $errorMessage)
    {
        $this->logSyncEvent("Error: {$errorMessage}", 'error', '❌');

        $this->syncHistory?->update([
            'status' => 'failed',
            'logs' => $this->accumulatedLogs,
            'finished_at' => Carbon::now(),
        ]);
    }
}
