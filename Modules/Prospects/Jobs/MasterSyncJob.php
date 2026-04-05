<?php

namespace Modules\Prospects\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Models\User;
use Filament\Notifications\Notification;
use Modules\Prospects\Jobs\SendMasterSyncFinishedNotificationJob;

final class MasterSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?int $userId = null,
        protected ?int $historyId = null
    ) {}

    public function handle(): void
    {
        // Chain all sync jobs sequentially
        Bus::chain([
            new ExecuteSyncJob('prospects:sync-lbfa-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-aft-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-hockey-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-tpv-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-val-clubs', $this->userId, $this->historyId),
            new ExecuteSyncJob('prospects:sync-rbfa-graphql', $this->userId, $this->historyId),
            new SendMasterSyncFinishedNotificationJob($this->userId, $this->historyId),
        ])->dispatch();
    }

    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('prospects-master-sync'))->releaseAfter(120),
        ];
    }
}
