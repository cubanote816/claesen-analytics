<?php

namespace Modules\Prospects\Traits;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Prospects\Models\SyncHistory;

trait LogsSyncEvents
{
    protected ?SyncHistory $syncHistory = null;

    protected array $accumulatedLogs = [];

    /** Per-run tallies: persisted = successful writes, failed = handled item failures. */
    protected int $persistedCount = 0;

    protected int $failedCount = 0;

    public function startSyncLog(?int $userId = null, ?int $historyId = null)
    {
        // A new run must not leak tallies from a previous invocation.
        $this->persistedCount = 0;
        $this->failedCount = 0;

        if ($historyId) {
            $this->syncHistory = SyncHistory::find($historyId);
        }

        if (! $this->syncHistory) {
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

        if ($type === 'error') {
            // Never leave failure evidence in a crash-loss window: flush immediately
            // instead of waiting for the batch threshold.
            $this->flushSyncLog();
        } elseif (count($this->accumulatedLogs) % 5 === 0) {
            $this->syncHistory?->update([
                'logs' => $this->accumulatedLogs,
            ]);
        }
    }

    protected function markPersisted(): void
    {
        $this->persistedCount++;
    }

    protected function markFailed(): void
    {
        $this->failedCount++;
    }

    public function flushSyncLog()
    {
        $this->syncHistory?->update([
            'logs' => $this->accumulatedLogs,
        ]);
    }

    public function finishSyncLog(int $recordsCount)
    {
        // Attempts-vs-persisted breakdown (finding 9): the command passes its
        // persisted tally as $recordsCount; the counters surface the failures.
        $breakdown = '';
        if ($this->persistedCount > 0 || $this->failedCount > 0) {
            $breakdown = sprintf(
                ' (processed %d | persisted %d | failed %d)',
                $this->persistedCount + $this->failedCount,
                $this->persistedCount,
                $this->failedCount
            );
        }

        $this->logSyncEvent("Synchronization completed. Processed {$recordsCount} records.{$breakdown}", 'success', '🏁');

        // increment(), not update(): the master chain shares one SyncHistory
        // row across all federations, each calling finishSyncLog() in turn.
        // An update() here would overwrite the running total with just this
        // command's count, so the row would end up reporting only the last
        // federation that ran. increment() adds this command's tally onto
        // whatever the previous commands in the chain already accumulated
        // (0 for a standalone, non-chained run).
        $this->syncHistory?->increment('records_count', $recordsCount, [
            'status' => 'completed',
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

    /**
     * Crash-safe lifecycle guard for a sync body. On ANY Throwable the active
     * SyncHistory is marked failed (with a useful message) and the exception is
     * re-thrown so queue workers still observe the failure. On success the
     * body's exit code is returned untouched — the guard never finishes or
     * fails the history on the body's behalf (the body owns start/finish).
     */
    protected function guardedSync(callable $body): int
    {
        try {
            $result = $body();

            return is_int($result) ? $result : Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->failSyncLog(sprintf(
                '%s (%s at %s:%d)',
                $e->getMessage(),
                $e::class,
                basename($e->getFile()),
                $e->getLine()
            ));

            throw $e;
        }
    }
}
