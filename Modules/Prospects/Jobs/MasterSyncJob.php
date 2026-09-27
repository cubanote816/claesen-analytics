<?php

namespace Modules\Prospects\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class MasterSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?int $userId = null,
        protected ?int $historyId = null
    ) {}

    public function handle(): void
    {
        $userId = $this->userId;
        $historyId = $this->historyId;

        // Chain all sync jobs sequentially
        Bus::chain([
            new ExecuteSyncJob('prospects:sync-lbfa-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-aft-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-hockey-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-tpv-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-val-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-rbfa-graphql', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-brussels-clubs', $this->userId, $this->historyId),
            new SendMasterSyncFinishedNotificationJob($this->userId, $this->historyId),
        ])->catch(static function (Throwable $exception) use ($userId, $historyId): void {
            if ($historyId) {
                MarkMasterSyncFailedJob::dispatch($userId, $historyId, $exception->getMessage());
            }
        })->dispatch();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('prospects-master-sync'))->releaseAfter(120),
        ];
    }
}
